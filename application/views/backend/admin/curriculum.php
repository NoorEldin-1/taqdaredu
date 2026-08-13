<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * مقرر الكورس — أقسامه ودروسه.
 *
 * أعيدت كتابتها بهيكل `tqa-*`. وأعطالها:
 *
 * ١ — **الإجراءات تظهر بالتمرير وحده.** `display-none` وjQuery
 *     `mouseenter` — أي لا تحرير ولا حذف لقسم أو درس من جوال أو لوح
 *     إطلاقا، ولا وصول إليها بلوحة المفاتيح. صارت ظاهرة دائما.
 * ٢ — **الأزرار كانت داخل نموذج تحرير الكورس.** زر `<button>` بلا سمة
 *     `type` نوعه `submit` ضمنا؛ فالضغط على «فرز الأقسام» كان يرسل
 *     نموذج الكورس كله ويعيد التوجيه. صار المقرر خارج النموذج تماما
 *     (انظر [course_edit.php])، وكل زر يعلن نوعه.
 * ٣ — **الحذف برابط GET** ينفذ بمجرد جلبه — لقسم كامل بدروسه. صار
 *     نموذج POST بتوكن ونافذة تأكيد.
 * ٤ — **أيقونات الدرس صور PNG** من `assets/backend/lesson_icon/` تحمل
 *     ملفا لكل صف؛ صارت أيقونات `tq_icon` مضمنة.
 * ٥ — **`$lesson['attachment_type']` بلا فحص** — درس نوعه فيديو لا
 *     يحمل العمود، فيقرأ فهرسا غائبا في كل صف.
 */
$tq_sections = $this->crud_model->get_section('course', $course_id)->result_array();

/** نوع الدرس يترجم إلى أيقونة واسم عربي. */
$tq_kind = function ($lesson) {
    if ($lesson['lesson_type'] === 'quiz') return array('check-badge', 'اختبار');

    $tq_att = isset($lesson['attachment_type']) ? (string) $lesson['attachment_type'] : '';
    switch ($tq_att) {
        case 'txt': return array('file-text', 'نص');
        case 'pdf':
        case 'doc': return array('file', 'مستند');
        case 'img': return array('image', 'صورة');
        default:    return array('play', 'فيديو');
    }
};
?>

<div class="tqa-toolbar">
    <button type="button" class="tqa-btn tqa-btn--primary"
            onclick="showAjaxModal('<?php echo site_url('modal/popup/section_add/' . (int) $course_id); ?>', 'إضافة قسم')">
        <?php echo tq_icon('plus', 16); ?> إضافة قسم
    </button>

    <button type="button" class="tqa-btn tqa-btn--ghost"
            onclick="showAjaxModal('<?php echo site_url('modal/popup/lesson_types/' . (int) $course_id); ?>', 'إضافة درس')">
        <?php echo tq_icon('plus', 16); ?> إضافة درس
    </button>

    <?php if ($tq_sections): ?>
        <button type="button" class="tqa-btn tqa-btn--ghost"
                onclick="showAjaxModal('<?php echo site_url('modal/popup/quiz_add/' . (int) $course_id); ?>', 'إضافة اختبار')">
            <?php echo tq_icon('plus', 16); ?> إضافة اختبار
        </button>

        <button type="button" class="tqa-btn tqa-btn--ghost"
                onclick="showLargeModal('<?php echo site_url('modal/popup/sort_section/' . (int) $course_id); ?>', 'ترتيب الأقسام')">
            <?php echo tq_icon('layers', 16); ?> ترتيب الأقسام
        </button>
    <?php endif; ?>
</div>

<?php if (empty($tq_sections)): ?>

    <div class="tqa-card tqa-card--flush">
        <?php tqa_empty(
            'لا أقسام في هذا الكورس بعد',
            'القسم وعاء الدروس. أضف قسما أولا، ثم ضع دروسه فيه — والدرس لا يضاف بلا قسم يحمله.',
            '', '', 'layers'
        ); ?>
    </div>

<?php else: ?>

    <div class="tqa-stack" style="max-inline-size:900px">
    <?php
    $tq_lesson_no = 0;
    $tq_quiz_no   = 0;

    foreach ($tq_sections as $tq_i => $tq_s):
        $tq_lessons = $this->crud_model->get_lessons('section', $tq_s['id'])->result_array();

        /* خطة الدراسة تعرض حين يختلف تاريخا البدء والانتهاء فعلا. */
        $tq_plan = (int) $tq_s['start_date'] !== (int) $tq_s['end_date']
                && (int) $tq_s['start_date'] > 0;
    ?>
        <section class="tqa-card tqa-card--flush">

            <div class="tqa-card__head">
                <span class="tqa-iconbox tqa-mint" aria-hidden="true"><?php echo tq_icon('layers', 20); ?></span>

                <div style="min-inline-size:0">
                    <h2 style="font:var(--tq-type-h2)">
                        <span style="color:var(--tq-text2);font-weight:500">قسم <span class="tqa-num"><?php echo $tq_i + 1; ?></span>:</span>
                        <?php echo html_escape($tq_s['title']); ?>
                    </h2>
                    <span class="tqa-media__sub">
                        <span class="tqa-num"><?php echo count($tq_lessons); ?></span> درسا
                        <?php if ($tq_plan): ?>
                            · خطة دراسة
                            <span class="tq-ltr" dir="ltr"><?php echo date('Y-m-d', (int) $tq_s['start_date']); ?></span>
                            —
                            <span class="tq-ltr" dir="ltr"><?php echo date('Y-m-d', (int) $tq_s['end_date']); ?></span>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="tqa-rowacts">
                    <?php if (count($tq_lessons) > 1): ?>
                        <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                                onclick="showLargeModal('<?php echo site_url('modal/popup/sort_lesson/' . (int) $tq_s['id']); ?>', 'ترتيب الدروس')">
                            <?php echo tq_icon('layers', 14); ?> ترتيب
                        </button>
                    <?php endif; ?>

                    <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                            onclick="showAjaxModal('<?php echo site_url('modal/popup/section_edit/' . (int) $tq_s['id'] . '/' . (int) $course_id); ?>', 'تعديل القسم')">
                        <?php echo tq_icon('edit', 14); ?> تعديل
                    </button>

                    <form method="post" action="<?php echo site_url('admin/sections/' . (int) $course_id . '/delete/' . (int) $tq_s['id']); ?>"
                          data-tqa-confirm-title="حذف القسم"
                          data-tqa-confirm="سيحذف «<?php echo html_escape($tq_s['title']); ?>» وكل دروسه. لا رجعة في هذا."
                          data-tqa-confirm-ok="نعم، احذف"
                          data-tqa-confirm-tone="danger">
                        <?php echo tq_csrf(); ?>
                        <button type="submit" class="tqa-btn tqa-btn--ghost tqa-btn--sm" style="color:var(--tq-danger)">
                            <?php echo tq_icon('trash', 14); ?>
                            <span class="tqa-sr">حذف القسم</span>
                        </button>
                    </form>
                </div>
            </div>

            <?php if (empty($tq_lessons)): ?>
                <p style="padding:var(--tq-space-xl);margin:0;font:var(--tq-type-caption);color:var(--tq-text2)">
                    لا دروس في هذا القسم بعد.
                </p>
            <?php else: ?>
                <ul style="list-style:none;margin:0;padding:0">
                <?php foreach ($tq_lessons as $tq_l):
                    $tq_is_quiz = $tq_l['lesson_type'] === 'quiz';
                    if ($tq_is_quiz) $tq_quiz_no++; else $tq_lesson_no++;
                    [$tq_ic, $tq_kindname] = $tq_kind($tq_l);
                ?>
                    <li style="display:flex;align-items:center;gap:var(--tq-space-m);
                               padding:var(--tq-space-m) var(--tq-space-xl);
                               border-block-start:1px solid var(--tq-line)">

                        <span class="tqa-iconbox <?php echo $tq_is_quiz ? 'tqa-peach' : 'tqa-sky'; ?>"
                              aria-hidden="true" style="inline-size:34px;block-size:34px">
                            <?php echo tq_icon($tq_ic, 17); ?>
                        </span>

                        <div style="flex:1;min-inline-size:0">
                            <span class="tqa-media__title"><?php echo html_escape($tq_l['title']); ?></span>
                            <span class="tqa-media__sub">
                                <?php echo $tq_is_quiz ? 'اختبار' : 'درس'; ?>
                                <span class="tqa-num"><?php echo $tq_is_quiz ? $tq_quiz_no : $tq_lesson_no; ?></span>
                                · <?php echo html_escape($tq_kindname); ?>
                            </span>
                        </div>

                        <div class="tqa-rowacts">
                            <?php if ($tq_is_quiz): ?>
                                <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                                        onclick="showLargeModal('<?php echo site_url('modal/popup/quiz_questions/' . (int) $tq_l['id']); ?>', 'أسئلة الاختبار')">
                                    <?php echo tq_icon('help', 14); ?> الأسئلة
                                </button>

                                <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                                        onclick="showAjaxModal('<?php echo site_url('modal/popup/quiz_edit/' . (int) $tq_l['id'] . '/' . (int) $course_id); ?>', 'تعديل الاختبار')">
                                    <?php echo tq_icon('edit', 14); ?> تعديل
                                </button>
                            <?php else: ?>
                                <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                                        onclick="showAjaxModal('<?php echo site_url('modal/popup/resource_files/' . (int) $tq_l['id']); ?>', 'ملفات الدرس')">
                                    <?php echo tq_icon('folder', 14); ?> الملفات
                                </button>

                                <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                                        onclick="showAjaxModal('<?php echo site_url('modal/popup/lesson_edit/' . (int) $tq_l['id'] . '/' . (int) $course_id); ?>', 'تعديل الدرس')">
                                    <?php echo tq_icon('edit', 14); ?> تعديل
                                </button>
                            <?php endif; ?>

                            <form method="post"
                                  action="<?php echo site_url('admin/lessons/' . (int) $course_id . '/delete/' . (int) $tq_l['id']); ?>"
                                  data-tqa-confirm-title="<?php echo $tq_is_quiz ? 'حذف الاختبار' : 'حذف الدرس'; ?>"
                                  data-tqa-confirm="سيحذف «<?php echo html_escape($tq_l['title']); ?>» نهائيا."
                                  data-tqa-confirm-ok="نعم، احذف"
                                  data-tqa-confirm-tone="danger">
                                <?php echo tq_csrf(); ?>
                                <button type="submit" class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                                        style="color:var(--tq-danger)">
                                    <?php echo tq_icon('trash', 14); ?>
                                    <span class="tqa-sr">حذف <?php echo html_escape($tq_l['title']); ?></span>
                                </button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
    </div>

<?php endif; ?>
