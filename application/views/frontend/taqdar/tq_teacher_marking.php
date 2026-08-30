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
$tq_title = t('الواجبات والتصحيح');
$tq_sub   = t('الاقتراح الآلي يسبقك، والاعتماد لك وحدك');
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

/* الواجبات — المسار الثاني للتقييم في هذه المنصة.
   كان الطالب يسلم واجبه من «مهامي» فلا يصل معلما أبدا: التصحيح كله على
   `quiz_results` والواجبات في `assessments`/`attempts`. فصار لها صفها
   هنا، بالنطاق نفسه وبالاعتماد نفسه. */
$tq_hw_queue    = $tq_mark->homework_queue($tq_uid);
$tq_hw_approved = $tq_mark->homework_recent($tq_uid, 8);

/* التصحيح المفرد: المعرف من الرابط لا يصدق — يعاد التحقق من نطاقه. */
$tq_rid    = (int) $this->input->get('result');
$tq_single = $tq_rid ? $tq_mark->attempt($tq_rid, $tq_uid) : null;

$tq_hwid   = (int) $this->input->get('hw');
$tq_hw_one = $tq_hwid ? $tq_mark->homework_attempt($tq_hwid, $tq_uid) : null;

include 'portal_open.php';
?>

<?php if ($tq_msg = tq_flash('flash_message')): ?>
    <p class="tq-card tq-section" role="status" style="border-inline-start:4px solid var(--tq-mint-ink)">
        <?php echo tq_iso(html_escape($tq_msg)); ?>
    </p>
<?php endif; ?>
<?php if ($tq_err = tq_flash('error_message')): ?>
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
                <?php echo tq_csrf(); ?>
                <input type="hidden" name="result_id" value="<?php echo (int) $tq_single['quiz_result_id']; ?>">

                <div class="tq-row" style="gap:var(--tq-space-l);margin-block-end:var(--tq-space-xl)">
                    <img class="tq-avatar tq-avatar--lg"
                         src="<?php echo tqs_person_img($tq_single['image']); ?>"
                         alt="<?php echo te('صورة ____', array(html_escape($tq_name))); ?>">
                    <div style="flex:1;min-inline-size:0">
                        <h2 class="tq-h2" style="margin:0"><?php echo html_escape($tq_name); ?></h2>
                        <p class="tq-caption" style="margin:0">
                            <?php echo html_escape($tq_single['quiz_title'] . ' · ' . $tq_single['course_title']); ?>
                        </p>
                    </div>
                    <?php echo $tq_is_appr
                        ? tq_badge('mastered', t('معتمدة'))
                        : tq_badge('due', t('ينتظر اعتمادك')); ?>
                </div>

                <?php if ($tq_is_appr): ?>
                    <div class="tq-pastel tq-pastel--mint" style="margin-block-end:var(--tq-space-xl)">
                        <span class="tq-pastel__label tq-micro"><?php echo t('درجة معتمدة'); ?></span>
                        <p class="tq-pastel__title" style="margin:var(--tq-space-s) 0 0;font:var(--tq-type-numeralXl)">
                            <?php echo tq_num(((float) $tq_single['teacher_score'] == (int) $tq_single['teacher_score']
                                ? (int) $tq_single['teacher_score'] : (float) $tq_single['teacher_score']) . ' / ' . $tq_total); ?>
                        </p>
                        <p class="tq-pastel__body tq-caption" style="margin:var(--tq-space-s) 0 0">
                            <?php echo tq_iso(t('اعتمدتها ') . tq_since((int) $tq_single['approved_at']) . t('، وهي ظاهرة للطالب الآن. تعديلها يحل محلها.')); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <div class="tq-pastel tq-pastel--sky" style="margin-block-end:var(--tq-space-xl)">
                    <span class="tq-pastel__label tq-micro"><?php echo t('اقتراح آلي للدرجة'); ?></span>
                    <p class="tq-pastel__title" style="margin:var(--tq-space-s) 0 0;font:var(--tq-type-numeralXl)">
                        <?php echo tq_num($tq_auto . ' / ' . $tq_total); ?>
                    </p>
                    <p class="tq-pastel__body tq-caption" style="margin:var(--tq-space-s) 0 0">
                        <?php echo t('محسوب من الأسئلة الموضوعية وحدها. التصحيح الآلي مساعد لا بديل — عدل الدرجة متى رأيت في الإجابة ما لا يراه الحاسوب.'); ?>
                        <?php if ($tq_essays > 0): ?>
                            <br><?php echo tq_iso(t('وفي هذا الاختبار ') . $tq_essays . t(' من الأسئلة لا يقرؤها السكربت أصلا.')); ?>
                        <?php endif; ?>
                    </p>
                </div>

                <div class="tq-grid tq-grid--2">
                    <div class="tq-field">
                        <label class="tq-field__label" for="tq-score"><?php echo t('الدرجة'); ?></label>
                        <input class="tq-input" id="tq-score" type="number" name="score"
                               min="0" max="<?php echo $tq_total; ?>" step="0.5" inputmode="decimal"
                               value="<?php echo html_escape((string) $tq_value); ?>" required>
                        <span class="tq-field__msg tq-field__hint">
                            <?php echo tq_iso(t('من 0 إلى ') . $tq_total . t(' درجة.')); ?>
                        </span>
                    </div>

                    <div class="tq-field">
                        <span class="tq-field__label"><?php echo t('حالة الهدف'); ?></span>
                        <p class="tq-input" style="display:flex;align-items:center;margin:0">
                            <?php echo tq_badge($tq_mastery['key'], $tq_mastery['label']); ?>
                        </p>
                        <span class="tq-field__msg tq-field__hint">
                            <?php echo tq_iso(t('تشتق من الدرجة وعتبة النجاح (') . $tq_pass . t('%) — لا تسأل عنها مرتين.')); ?>
                        </span>
                    </div>
                </div>

                <div class="tq-field">
                    <label class="tq-field__label" for="tq-note"><?php echo t('ملاحظتك للطالب'); ?></label>
                    <textarea class="tq-textarea" id="tq-note" name="note" rows="4"
                              placeholder="<?php echo te('اكتب ما يفعله في المحاولة القادمة، لا ما أخطأ فيه فقط'); ?>"><?php
                        echo html_escape((string) $tq_single['teacher_note']);
                    ?></textarea>
                    <span class="tq-field__msg tq-field__hint"><?php echo t('تظهر للطالب مع الدرجة، ولولي أمره ضمن ملاحظات المعلمين.'); ?></span>
                </div>

                <div class="tq-row" style="gap:var(--tq-space-m);flex-wrap:wrap">
                    <button class="tq-btn tq-btn--mastery" type="submit" aria-describedby="tq-approve-note">
                        <?php echo $tq_is_appr ? t('تحديث الدرجة المعتمدة') : t('اعتماد الدرجة'); ?>
                    </button>
                    <a class="tq-btn tq-btn--secondary" href="<?php echo base_url('teacher/marking'); ?>"><?php echo t('رجوع إلى الصف'); ?></a>
                </div>
                <p class="tq-field__msg tq-field__hint" id="tq-approve-note" style="margin-block-start:var(--tq-space-m)">
                    <?php if ($tq_essays > 0): ?>
                        <?php echo t('الدرجة النهائية للسؤال المقالي لا تظهر للطالب قبل اعتمادك — والحجب مطبق في الاستعلام لا في الشاشة.'); ?>
                    <?php else: ?>
                        <?php echo t('كل أسئلة هذا الاختبار موضوعية، فدرجته ظاهرة لصاحبها. واعتمادك يثبتها ويضيف ملاحظتك إليها.'); ?>
                    <?php endif; ?>
                </p>
            </form>

            <?php /* سحب الاعتماد — نموذج مستقل لأن النماذج لا تتداخل.
                     `Taqdar_marking_model::unapprove()` كانت مكتوبة بلا باب:
                     حاجز يوضع ولا يرفع، ومن اعتمد بالخطأ لا يملك التراجع.
                     ويعرض بعد الاعتماد وحده — زر يسحب ما لم يعتمد لا معنى له. */ ?>
            <?php if ($tq_is_appr): ?>
                <form class="tq-card tq-section" method="post"
                      action="<?php echo base_url('teacher/marking/approve'); ?>"
                      data-tq-confirm-title="<?php echo te('سحب اعتماد درجة ____؟', array(html_escape($tq_name))); ?>"
                      data-tq-confirm="<?php echo te('تعود الدرجة محجوبة عن الطالب حتى تعتمدها من جديد.'); ?>"
                      data-tq-confirm-note="<?php echo te('الدرجة والملاحظة تبقيان محفوظتين كما هما، ويرفع الاعتماد وحده.'); ?>"
                      data-tq-confirm-ok="<?php echo te('أسحب الاعتماد'); ?>"
                      data-tq-confirm-tone="danger">
                    <?php echo tq_csrf(); ?>
                    <input type="hidden" name="result_id" value="<?php echo (int) $tq_single['quiz_result_id']; ?>">
                    <input type="hidden" name="act" value="unapprove">
                    <div class="tq-row tq-row--between" style="gap:var(--tq-space-m);flex-wrap:wrap">
                        <span class="tq-caption" style="flex:1;min-inline-size:14rem">
                            <?php echo t('اعتمدت بالخطأ؟ سحب الاعتماد يعيد الدرجة إلى الحجب عن الطالب.'); ?>
                        </span>
                        <button class="tq-btn tq-btn--secondary tq-btn--sm" type="submit"><?php echo t('سحب الاعتماد'); ?></button>
                    </div>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <?php /* ---- تصحيح واجب مفرد ------------------------------------ */ ?>
        <?php if ($tq_hw_one): ?>
            <?php
            $tq_hname   = trim($tq_hw_one['first_name'] . ' ' . $tq_hw_one['last_name']);
            $tq_htotal  = max(1, (int) $tq_hw_one['total_marks']);
            $tq_hauto   = $tq_hw_one['score'] === null ? null : (float) $tq_hw_one['score'];
            $tq_happr   = !empty($tq_hw_one['approved_at']);
            $tq_hvalue  = ($tq_happr && $tq_hw_one['teacher_score'] !== null)
                            ? (float) $tq_hw_one['teacher_score']
                            : ($tq_hauto === null ? '' : $tq_hauto);
            $tq_hmast   = $tq_hvalue === '' ? null : $tq_mark->mastery($tq_hvalue, $tq_htotal);
            $tq_hpass   = (int) $tq_hw_one['pass_mark'];
            ?>
            <form class="tq-card tq-card--panel tq-section" method="post"
                  action="<?php echo base_url('teacher/marking/homework'); ?>">
                <?php echo tq_csrf(); ?>
                <input type="hidden" name="attempt_id" value="<?php echo (int) $tq_hw_one['id']; ?>">

                <div class="tq-row" style="gap:var(--tq-space-l);margin-block-end:var(--tq-space-xl)">
                    <img class="tq-avatar tq-avatar--lg"
                         src="<?php echo tqs_person_img($tq_hw_one['image']); ?>"
                         alt="<?php echo te('صورة ____', array(html_escape($tq_hname))); ?>">
                    <div style="flex:1;min-inline-size:0">
                        <h2 class="tq-h2" style="margin:0"><?php echo html_escape($tq_hname); ?></h2>
                        <p class="tq-caption" style="margin:0">
                            <?php echo html_escape(t('واجب: ') . $tq_hw_one['lesson_title'] . ' · ' . $tq_hw_one['course_title']); ?>
                        </p>
                    </div>
                    <?php echo $tq_happr ? tq_badge('mastered', t('معتمد')) : tq_badge('due', t('ينتظر اعتمادك')); ?>
                </div>

                <div class="tq-pastel tq-pastel--sand" style="margin-block-end:var(--tq-space-xl)">
                    <span class="tq-pastel__label tq-micro"><?php echo t('ما سجله النظام'); ?></span>
                    <p class="tq-pastel__title" style="margin:var(--tq-space-s) 0 0;font:var(--tq-type-numeralXl)">
                        <?php echo $tq_hauto === null
                            ? '—'
                            : tq_num(($tq_hauto == (int) $tq_hauto ? (int) $tq_hauto : $tq_hauto) . '%'); ?>
                    </p>
                    <p class="tq-pastel__body tq-caption" style="margin:var(--tq-space-s) 0 0">
                        <?php echo tq_iso(t('سلم ') . tq_since(strtotime((string) $tq_hw_one['submitted_at']))
                            . t(' · درجة العبور ') . $tq_hpass . '%.'); ?>
                        <?php echo t('والواجب عمل يقرؤه معلم — درجته لا تظهر لصاحبه قبل اعتمادك.'); ?>
                    </p>
                </div>

                <div class="tq-grid tq-grid--2">
                    <div class="tq-field">
                        <label class="tq-field__label" for="tq-hw-score"><?php echo t('الدرجة (نسبة مئوية)'); ?></label>
                        <input class="tq-input" id="tq-hw-score" type="number" name="score"
                               min="0" max="100" step="1" inputmode="numeric"
                               value="<?php echo html_escape((string) $tq_hvalue); ?>" required>
                        <span class="tq-field__msg tq-field__hint">
                            <?php echo tq_iso(t('نسبة مئوية من 0 إلى 100 — وهو مقياس الواجبات في المنصة، كما يقرؤه الطالب في «مهامي».')); ?>
                        </span>
                    </div>

                    <div class="tq-field">
                        <span class="tq-field__label"><?php echo t('حالة الهدف'); ?></span>
                        <p class="tq-input" style="display:flex;align-items:center;margin:0">
                            <?php echo $tq_hmast ? tq_badge($tq_hmast['key'], $tq_hmast['label']) : '—'; ?>
                        </p>
                        <span class="tq-field__msg tq-field__hint">
                            <?php echo tq_iso(t('العبور بعتبة هذا الواجب (') . $tq_hpass . t('%)، وحالة الهدف بعتبة المنصة (') . $tq_pass . '%).'); ?>
                        </span>
                    </div>
                </div>

                <div class="tq-field">
                    <label class="tq-field__label" for="tq-hw-note"><?php echo t('ملاحظتك للطالب'); ?></label>
                    <textarea class="tq-textarea" id="tq-hw-note" name="note" rows="4"
                              placeholder="<?php echo te('اكتب ما يفعله في المرة القادمة، لا ما أخطأ فيه فقط'); ?>"><?php
                        echo html_escape((string) $tq_hw_one['teacher_note']);
                    ?></textarea>
                    <span class="tq-field__msg tq-field__hint"><?php echo t('تظهر للطالب مع الدرجة، ولولي أمره ضمن ملاحظات المعلمين.'); ?></span>
                </div>

                <div class="tq-row" style="gap:var(--tq-space-m);flex-wrap:wrap">
                    <button class="tq-btn tq-btn--mastery" type="submit">
                        <?php echo $tq_happr ? t('تحديث درجة الواجب') : t('اعتماد درجة الواجب'); ?>
                    </button>
                    <a class="tq-btn tq-btn--secondary" href="<?php echo base_url('teacher/marking'); ?>"><?php echo t('رجوع إلى الصف'); ?></a>
                </div>
            </form>

            <?php if ($tq_happr): ?>
                <form class="tq-card tq-section" method="post"
                      action="<?php echo base_url('teacher/marking/homework'); ?>"
                      data-tq-confirm-title="<?php echo te('سحب اعتماد واجب ____؟', array(html_escape($tq_hname))); ?>"
                      data-tq-confirm="<?php echo te('تعود الدرجة محجوبة عن الطالب حتى تعتمدها من جديد.'); ?>"
                      data-tq-confirm-ok="<?php echo te('أسحب الاعتماد'); ?>"
                      data-tq-confirm-tone="danger">
                    <?php echo tq_csrf(); ?>
                    <input type="hidden" name="attempt_id" value="<?php echo (int) $tq_hw_one['id']; ?>">
                    <input type="hidden" name="act" value="unapprove">
                    <div class="tq-row tq-row--between" style="gap:var(--tq-space-m);flex-wrap:wrap">
                        <span class="tq-caption" style="flex:1;min-inline-size:14rem">
                            <?php echo t('اعتمدت بالخطأ؟ سحب الاعتماد يعيد الدرجة إلى الحجب عن الطالب.'); ?>
                        </span>
                        <button class="tq-btn tq-btn--secondary tq-btn--sm" type="submit"><?php echo t('سحب الاعتماد'); ?></button>
                    </div>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <section aria-labelledby="tq-queue-h">
            <div class="tq-sectionhead">
                <h2 id="tq-queue-h"><?php echo t('صف التصحيح — الاختبارات'); ?></h2>
                <?php if ($tq_queue): ?>
                    <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_queue) . TQ_PDI; ?></span>
                <?php endif; ?>
            </div>

            <?php if ($tq_queue): ?>
                <div class="tq-card">
                    <p class="tq-caption" style="margin-block-end:var(--tq-space-l)">
                        <?php echo t('الأقدم أولا: الطالب الذي انتظر أطول يصحح أولا.'); ?>
                    </p>
                    <table class="tq-table">
                        <caption class="tq-sr"><?php echo t('المحاولات المسلمة التي تنتظر اعتمادك'); ?></caption>
                        <thead>
                            <tr>
                                <th scope="col"><?php echo t('الطالب'); ?></th>
                                <th scope="col"><?php echo t('الاختبار'); ?></th>
                                <th scope="col"><?php echo t('الاقتراح الآلي'); ?></th>
                                <th scope="col"><?php echo t('انتظر'); ?></th>
                                <th scope="col"><span class="tq-sr"><?php echo t('إجراءات'); ?></span></th>
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
                                            <?php echo t('صحح'); ?>
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
                    <h3 class="tq-empty__title"><?php echo t('لا شيء ينتظر تصحيحا'); ?></h3>
                    <p class="tq-empty__text">
                        <?php echo t('حين يسلم أحد طلابك اختبارا أو واجبا في كورساتك، يظهر هنا مرتبا بالأقدم انتظارا، ومعه اقتراح آلي للدرجة تعتمده أو تعدله.'); ?>
                    </p>
                    <a class="tq-btn tq-btn--secondary" href="<?php echo base_url('teacher/students'); ?>"><?php echo t('طلابي'); ?></a>
                </div>
            <?php endif; ?>
        </section>

        <?php /* ---- صف الواجبات: مصدر آخر ودرجة أخرى، فقسم مستقل لا مدمج ---- */ ?>
        <section aria-labelledby="tq-hwq-h" class="tq-section">
            <div class="tq-sectionhead">
                <h2 id="tq-hwq-h"><?php echo t('صف التصحيح — الواجبات'); ?></h2>
                <?php if ($tq_hw_queue): ?>
                    <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_hw_queue) . TQ_PDI; ?></span>
                <?php endif; ?>
            </div>

            <?php if ($tq_hw_queue): ?>
                <div class="tq-card">
                    <p class="tq-caption" style="margin-block-end:var(--tq-space-l)">
                        <?php echo t('الواجب عمل يقرؤه معلم لا سكربت — ودرجته لا تظهر لصاحبه قبل اعتمادك. والأقدم تسليما أولا.'); ?>
                    </p>
                    <table class="tq-table">
                        <caption class="tq-sr"><?php echo t('الواجبات المسلمة التي تنتظر اعتمادك'); ?></caption>
                        <thead>
                            <tr>
                                <th scope="col"><?php echo t('الطالب'); ?></th>
                                <th scope="col"><?php echo t('الواجب'); ?></th>
                                <th scope="col"><?php echo t('ما سجله النظام'); ?></th>
                                <th scope="col"><?php echo t('انتظر'); ?></th>
                                <th scope="col"><span class="tq-sr"><?php echo t('إجراءات'); ?></span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tq_hw_queue as $tq_h): ?>
                                <?php
                                $tq_hn  = trim($tq_h['first_name'] . ' ' . $tq_h['last_name']);
                                $tq_ht  = max(1, (int) $tq_h['total_marks']);
                                $tq_hs  = $tq_h['score'] === null ? null : (float) $tq_h['score'];
                                $tq_hts = strtotime((string) $tq_h['submitted_at']);
                                ?>
                                <tr>
                                    <td data-label="الطالب">
                                        <span class="tq-strong" style="color:var(--tq-navy)"><?php echo html_escape($tq_hn); ?></span>
                                        <span class="tq-micro" style="display:block"><?php echo html_escape($tq_h['course_title']); ?></span>
                                    </td>
                                    <td data-label="الواجب"><?php echo html_escape($tq_h['lesson_title']); ?></td>
                                    <td data-label="ما سجله النظام">
                                        <?php echo $tq_hs === null
                                            ? t('<span class="tq-caption">لم يسجل</span>')
                                            : tq_num(($tq_hs == (int) $tq_hs ? (int) $tq_hs : $tq_hs) . '%', 'tq-num--sm'); ?>
                                    </td>
                                    <td data-label="انتظر"><?php echo html_escape(tq_since($tq_hts)); ?></td>
                                    <td data-label="إجراءات">
                                        <a class="tq-btn tq-btn--primary tq-btn--sm"
                                           href="<?php echo base_url('teacher/marking'); ?>?hw=<?php echo (int) $tq_h['id']; ?>">
                                            <?php echo t('صحح'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="tq-card tq-empty">
                    <span class="tq-icon-box tq-pastel--mint" style="color:var(--tq-mint-ink)" aria-hidden="true"><?php echo tq_icon('clipboard', 24); ?></span>
                    <h3 class="tq-empty__title"><?php echo t('لا واجب ينتظر تصحيحا'); ?></h3>
                    <p class="tq-empty__text">
                        <?php echo t('حين يسلم أحد طلابك واجبا في دروس كورساتك يظهر هنا بالأقدم انتظارا، فتضع درجته وملاحظتك عليه.'); ?>
                    </p>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($tq_hw_approved): ?>
            <section aria-labelledby="tq-hwa-h" class="tq-section">
                <div class="tq-sectionhead">
                    <h2 id="tq-hwa-h"><?php echo t('واجبات اعتمدتها'); ?></h2>
                    <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_hw_approved) . TQ_PDI; ?></span>
                </div>
                <div class="tq-card">
                    <table class="tq-table">
                        <caption class="tq-sr"><?php echo t('آخر درجات الواجبات التي اعتمدتها'); ?></caption>
                        <thead>
                            <tr>
                                <th scope="col"><?php echo t('الطالب'); ?></th>
                                <th scope="col"><?php echo t('الواجب'); ?></th>
                                <th scope="col"><?php echo t('الدرجة المعتمدة'); ?></th>
                                <th scope="col"><?php echo t('منذ'); ?></th>
                                <th scope="col"><span class="tq-sr"><?php echo t('إجراءات'); ?></span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tq_hw_approved as $tq_h): ?>
                                <?php
                                $tq_hn = trim($tq_h['first_name'] . ' ' . $tq_h['last_name']);
                                $tq_ht = max(1, (int) $tq_h['total_marks']);
                                $tq_hs = (float) $tq_h['teacher_score'];
                                $tq_hm = $tq_mark->mastery($tq_hs, $tq_ht);
                                ?>
                                <tr>
                                    <td data-label="الطالب">
                                        <span class="tq-strong" style="color:var(--tq-navy)"><?php echo html_escape($tq_hn); ?></span>
                                        <span class="tq-micro" style="display:block"><?php echo html_escape($tq_h['course_title']); ?></span>
                                    </td>
                                    <td data-label="الواجب"><?php echo html_escape($tq_h['lesson_title']); ?></td>
                                    <td data-label="الدرجة المعتمدة">
                                        <?php echo tq_num(($tq_hs == (int) $tq_hs ? (int) $tq_hs : $tq_hs) . '%', 'tq-num--sm'); ?>
                                        <?php echo tq_badge($tq_hm['key'], $tq_hm['label']); ?>
                                    </td>
                                    <td data-label="منذ"><?php echo html_escape(tq_since((int) $tq_h['approved_at'])); ?></td>
                                    <td data-label="إجراءات">
                                        <a class="tq-btn tq-btn--secondary tq-btn--sm"
                                           href="<?php echo base_url('teacher/marking'); ?>?hw=<?php echo (int) $tq_h['id']; ?>">
                                            <?php echo t('راجع'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($tq_approved): ?>
            <section aria-labelledby="tq-approved-h" class="tq-section">
                <div class="tq-sectionhead">
                    <h2 id="tq-approved-h"><?php echo t('اعتمدتها أخيرا'); ?></h2>
                    <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_approved) . TQ_PDI; ?></span>
                </div>
                <div class="tq-card">
                    <table class="tq-table">
                        <caption class="tq-sr"><?php echo t('آخر الدرجات التي اعتمدتها'); ?></caption>
                        <thead>
                            <tr>
                                <th scope="col"><?php echo t('الطالب'); ?></th>
                                <th scope="col"><?php echo t('الاختبار'); ?></th>
                                <th scope="col"><?php echo t('الدرجة المعتمدة'); ?></th>
                                <th scope="col"><?php echo t('منذ'); ?></th>
                                <th scope="col"><span class="tq-sr"><?php echo t('إجراءات'); ?></span></th>
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
                                            <?php echo t('راجع'); ?>
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
            <span class="tq-pastel__label tq-micro"><?php echo t('حد التصحيح الآلي'); ?></span>
            <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                <?php echo t('التصحيح الآلي مساعد لا بديل. يقرأ الاختيار الصحيح، ولا يقرأ محاولة الطالب أن يشرح فكرته بعبارة أخرى.'); ?>
            </p>
            <p class="tq-pastel__body tq-caption" style="margin:var(--tq-space-m) 0 0">
                <?php echo t('ولذلك الدرجة النهائية للمقالي تظهر للطالب بعد اعتماد المعلم.'); ?>
            </p>
        </div>

        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title"><?php echo t('حالة الصف'); ?></h2></div>
            <ul class="tq-stack">
                <li class="tq-row tq-row--between">
                    <span class="tq-caption"><?php echo t('اختبارات تنتظر'); ?></span>
                    <?php echo tq_num(count($tq_queue)); ?>
                </li>
                <li class="tq-row tq-row--between">
                    <span class="tq-caption"><?php echo t('واجبات تنتظر'); ?></span>
                    <?php echo tq_num(count($tq_hw_queue)); ?>
                </li>
                <li class="tq-row tq-row--between">
                    <span class="tq-caption"><?php echo t('أقدم انتظار'); ?></span>
                    <span class="tq-caption" style="color:var(--tq-navy)">
                        <?php
                        /* الأقدم بين الصفين لا في أحدهما: المعلم يريد أن يعرف
                           أطول من انتظره، لا أطول من انتظره في جدول بعينه. */
                        $tq_oldest = null;
                        if ($tq_queue)    $tq_oldest = (int) $tq_queue[0]['date_added'];
                        if ($tq_hw_queue) {
                            $tq_h0 = strtotime((string) $tq_hw_queue[0]['submitted_at']);
                            if ($tq_oldest === null || $tq_h0 < $tq_oldest) $tq_oldest = $tq_h0;
                        }
                        echo $tq_oldest ? html_escape(tq_since($tq_oldest)) : '—';
                        ?>
                    </span>
                </li>
                <li class="tq-row tq-row--between">
                    <span class="tq-caption"><?php echo t('عتبة النجاح'); ?></span>
                    <span class="tq-caption" style="color:var(--tq-navy)"><?php echo tq_iso($tq_pass . '%'); ?></span>
                </li>
            </ul>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
