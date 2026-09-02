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

/* أعداد أسئلة اختبارات المقرر — استعلام واحد لا واحد لكل درس. */
$CI = get_instance();
$CI->load->model('taqdar_quiz_model', 'tq_quiz');
$tq_qcounts = $CI->tq_quiz->counts_for_course((int) $course_id);

/* TQ-DURATION — دروس تخالف مشغلات الطلاب مدتها المكتوبة.
   والمدة أساس القفل لا حلية: مكتوب أطول من المقطع يعني درسا تاليا لا
   يفتح أبدا. واستعلام واحد للمقرر كله، واللوح نفسه في بوابة المعلم. */
$CI->load->model('taqdar_curriculum_model', 'tq_curric');
$tq_durflags = $CI->tq_curric->duration_conflicts((int) $course_id);

/** نوع الدرس يترجم إلى أيقونة واسم عربي. */
$tq_kind = function ($lesson) {
    if ($lesson['lesson_type'] === 'quiz') return array('check-badge', t('اختبار'));

    $tq_att = isset($lesson['attachment_type']) ? (string) $lesson['attachment_type'] : '';
    switch ($tq_att) {
        case 'txt': return array('file-text', t('نص'));
        case 'pdf':
        case 'doc': return array('file', t('مستند'));
        case 'img': return array('image', t('صورة'));
        default:    return array('play', t('فيديو'));
    }
};
?>

<div class="tqa-toolbar">
    <button type="button" class="tqa-btn tqa-btn--primary"
            onclick="showAjaxModal('<?php echo site_url('modal/popup/section_add/' . (int) $course_id); ?>', 'إضافة قسم')">
        <?php echo tq_icon('plus', 16); ?> <?php echo t('إضافة قسم'); ?>
    </button>

    <button type="button" class="tqa-btn tqa-btn--ghost"
            onclick="showAjaxModal('<?php echo site_url('modal/popup/lesson_types/' . (int) $course_id); ?>', 'إضافة درس')">
        <?php echo tq_icon('plus', 16); ?> <?php echo t('إضافة درس'); ?>
    </button>

    <?php if ($tq_sections): ?>
        <button type="button" class="tqa-btn tqa-btn--ghost"
                onclick="showAjaxModal('<?php echo site_url('modal/popup/quiz_add/' . (int) $course_id); ?>', 'إضافة اختبار')">
            <?php echo tq_icon('plus', 16); ?> <?php echo t('إضافة اختبار'); ?>
        </button>

        <button type="button" class="tqa-btn tqa-btn--ghost"
                onclick="showLargeModal('<?php echo site_url('modal/popup/sort_section/' . (int) $course_id); ?>', 'ترتيب الأقسام')">
            <?php echo tq_icon('layers', 16); ?> <?php echo t('ترتيب الأقسام'); ?>
        </button>
    <?php endif; ?>
</div>

<?php if (empty($tq_sections)): ?>

    <div class="tqa-card tqa-card--flush">
        <?php tqa_empty(
            t('لا أقسام في هذا الكورس بعد'),
            t('القسم وعاء الدروس. أضف قسما أولا، ثم ضع دروسه فيه — والدرس لا يضاف بلا قسم يحمله.'),
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
                        <span style="color:var(--tq-text2);font-weight:500"><?php echo t('قسم'); ?> <span class="tqa-num"><?php echo $tq_i + 1; ?></span>:</span>
                        <?php echo html_escape($tq_s['title']); ?>
                    </h2>
                    <span class="tqa-media__sub">
                        <span class="tqa-num"><?php echo count($tq_lessons); ?></span> <?php echo t('درسا'); ?>
                        <?php if ($tq_plan): ?>
                            <?php echo t('· خطة دراسة'); ?>
                            <span class="tq-ltr" dir="ltr"><?php echo date('Y-m-d', (int) $tq_s['start_date']); ?></span>
                            —
                            <span class="tq-ltr" dir="ltr"><?php echo date('Y-m-d', (int) $tq_s['end_date']); ?></span>
                        <?php endif; ?>
                    </span>
                </div>

                <?php
                /* TQ-ROW-CLUTTER — أدوات القسم قائمة واحدة.
                   كانت ثلاثة أزرار في ترويسة كل قسم؛ وفي مقرر من ثمانية
                   أقسام هي أربعة وعشرون زرا تزاحم عناوين الأقسام نفسها،
                   وهي ما جاء المسؤول ليقرأه. */
                $tq_sacts = array();
                if (count($tq_lessons) > 1):
                    $tq_sacts[] = array(
                        'label'   => t('ترتيب الدروس'),
                        'sub'     => t('اسحب لتغيير التسلسل'),
                        'icon'    => 'layers',
                        'onclick' => "showLargeModal('" . site_url('modal/popup/sort_lesson/' . (int) $tq_s['id'])
                                   . "', '" . t('ترتيب الدروس') . "')",
                    );
                endif;
                $tq_sacts[] = array(
                    'label'   => t('تعديل القسم'),
                    'icon'    => 'edit',
                    'onclick' => "showAjaxModal('" . site_url('modal/popup/section_edit/' . (int) $tq_s['id'] . '/' . (int) $course_id)
                               . "', '" . t('تعديل القسم') . "')",
                );
                $tq_sacts[] = array('sep' => true);
                $tq_sacts[] = array(
                    'label'   => t('حذف القسم'),
                    'sub'     => t('ومعه كل دروسه'),
                    'icon'    => 'trash',
                    'tone'    => 'danger',
                    'action'  => 'admin/sections/' . (int) $course_id . '/delete/' . (int) $tq_s['id'],
                    'confirm' => array(
                        'title' => t('حذف القسم'),
                        'body'  => t('سيحذف «____» وكل دروسه. لا رجعة في هذا.', array($tq_s['title'])),
                        'ok'    => t('نعم، احذف'),
                        'tone'  => 'danger',
                    ),
                );
                echo tqa_rowmenu($tq_sacts, array(
                    'title' => $tq_s['title'],
                    'sub'   => t('قسم') . ' · ' . count($tq_lessons) . ' ' . t('درسا'),
                ));
                ?>
            </div>

            <?php if (empty($tq_lessons)): ?>
                <p style="padding:var(--tq-space-xl);margin:0;font:var(--tq-type-caption);color:var(--tq-text2)">
                    <?php echo t('لا دروس في هذا القسم بعد.'); ?>
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
                                <?php echo $tq_is_quiz ? t('اختبار') : t('درس'); ?>
                                <span class="tqa-num"><?php echo $tq_is_quiz ? $tq_quiz_no : $tq_lesson_no; ?></span>
                                · <?php echo html_escape($tq_kindname); ?>
                            </span>
                        </div>

                        <?php
                        /* TQ-ROW-CLUTTER — أدوات الدرس قائمة واحدة.

                           كانت أربعة أزرار في سطر كل درس (الاختبار ·
                           الملفات · تعديل · حذف)، ومقرر من ستين درسا هو
                           **مئتان وأربعون زرا** في شاشة واحدة: تأخذ نصف
                           عرض السطر فينضغط عنوان الدرس — وهو ما يقرأ —
                           ويقف الحذف بجوار التعديل بحجمه ولونه إلا حبره.

                           والفعل المقصود من كل نوع يقرأ أولا بلون الإتقان:
                           الأسئلة للاختبار، والاختبار للدرس. */
                        $tq_lacts = array();

                        if ($tq_is_quiz):
                            $tq_lacts[] = array(
                                'label'   => t('أسئلة الاختبار'),
                                'icon'    => 'help',
                                'tone'    => 'go',
                                'onclick' => "showLargeModal('" . site_url('modal/popup/quiz_questions/' . (int) $tq_l['id'])
                                           . "', '" . t('أسئلة الاختبار') . "')",
                            );
                            $tq_lacts[] = array(
                                'label'   => t('تعديل الاختبار'),
                                'icon'    => 'edit',
                                'onclick' => "showAjaxModal('" . site_url('modal/popup/quiz_edit/' . (int) $tq_l['id'] . '/' . (int) $course_id)
                                           . "', '" . t('تعديل الاختبار') . "')",
                            );
                        else:
                            /* اختبار الدرس — بجوار درسه لا في شاشة بعيدة: هو
                               الذي يفتح الدرس التالي، فموضعه حيث يبنى الدرس.
                               والعدد في البند: «الاختبار» وحدها لا تقول أفيه
                               أسئلة أم لا. وهذه الشاشة نفسها عند المعلم. */
                            $tq_qn = $tq_qcounts[(int) $tq_l['id']] ?? 0;
                            $tq_lacts[] = array(
                                'label' => $tq_qn > 0 ? t('اختبار الدرس') : t('أضف اختبارا'),
                                'sub'   => $tq_qn > 0
                                         ? t('____ سؤالا', array($tq_qn))
                                         : t('بلا اختبار لا يفتح الدرس التالي'),
                                'icon'  => 'help',
                                'tone'  => 'go',
                                'href'  => site_url('taqdar_admin/lesson_quiz/' . (int) $tq_l['id']),
                            );
                            $tq_lacts[] = array(
                                'label'   => t('ملفات الدرس'),
                                'icon'    => 'folder',
                                'onclick' => "showAjaxModal('" . site_url('modal/popup/resource_files/' . (int) $tq_l['id'])
                                           . "', '" . t('ملفات الدرس') . "')",
                            );
                            $tq_lacts[] = array(
                                'label'   => t('تعديل الدرس'),
                                'icon'    => 'edit',
                                'onclick' => "showAjaxModal('" . site_url('modal/popup/lesson_edit/' . (int) $tq_l['id'] . '/' . (int) $course_id)
                                           . "', '" . t('تعديل الدرس') . "')",
                            );
                        endif;

                        $tq_lacts[] = array('sep' => true);
                        $tq_lacts[] = array(
                            'label'   => $tq_is_quiz ? t('حذف الاختبار') : t('حذف الدرس'),
                            'icon'    => 'trash',
                            'tone'    => 'danger',
                            'action'  => 'admin/lessons/' . (int) $course_id . '/delete/' . (int) $tq_l['id'],
                            'confirm' => array(
                                'title' => $tq_is_quiz ? t('حذف الاختبار') : t('حذف الدرس'),
                                'body'  => t('سيحذف «____» نهائيا.', array($tq_l['title'])),
                                'ok'    => t('نعم، احذف'),
                                'tone'  => 'danger',
                            ),
                        );

                        echo tqa_rowmenu($tq_lacts, array(
                            'title' => $tq_l['title'],
                            'sub'   => ($tq_is_quiz ? t('اختبار') : t('درس')) . ' · ' . $tq_kindname,
                        ));
                        ?>
                    </li>
                    <?php if (isset($tq_durflags[(int) $tq_l['id']])): ?>
                        <li style="padding:0 var(--tq-space-xl) var(--tq-space-m);
                                   border-block-start:1px solid var(--tq-line)">
                            <?php tq_cur_duration_flag($tq_durflags[(int) $tq_l['id']], 'tqa'); ?>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
    </div>

<?php endif; ?>
