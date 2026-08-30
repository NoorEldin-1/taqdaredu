<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * مقرر الكورس عند المعلم — مرآة تبويب «المقرر» في اللوحة.
 *
 * الطلب: «نفس الشاشات اللي في الكورسات وإضافة الدروس تكون موجودة بالظبط
 * عند المدرس». وكان عند المعلم شيء واحد فقط: نموذج رفع درس بنوعين من
 * عشرة، بلا تعديل ولا حذف ولا ترتيب، **وبلا أقسام إطلاقا** — فالقسم
 * وعاء الدرس، ومن لا يملك إنشاء وعاء لا يملك بناء مقرر.
 *
 * وهذه الشاشة تعطيه ما تعطيه اللوحة حرفا بحرف، لأن الحقول تطبع من
 * `lesson_types()` نفسها التي تطبع بها اللوحة — انظر
 * [taqdar_curriculum_helper.php]. فالنوع الذي يضاف غدا يظهر هنا وهناك
 * معا، ولا يبقى موضع ينسى.
 *
 * وثلاثة فروق عن اللوحة، كلها مقصودة:
 *
 * ١ — **النشر ليس بيده.** «احفظ وأرسل للمراجعة» مكان «احفظ وانشر»،
 *     والقرار في `Taqdar_curriculum_model::may_publish()` لا هنا.
 * ٢ — **تعديل درس منشور لا يخفيه.** يودع اقتراحا، ويقال له ذلك قبل أن
 *     يحفظ لا بعده.
 * ٣ — **سبب الرد يعرض على الدرس نفسه.** المسؤول كتبه، والمعلم يقرؤه في
 *     الموضع الذي يصلح فيه — لا في إشعار يمر.
 *
 * والنوافذ `<dialog>` أصلية لا مكتبة: البوابة بلا Bootstrap، والنافذة
 * الأصلية تغلق بـEsc وتحبس التركيز وتعمل بلا سطر جافاسكربت للفتح.
 */

$tq_nav   = 'courses';
$tq_role  = 'teacher';
$tq_icon  = 'layers';

$tq_uid = (int) $this->session->userdata('user_id');
$tq_cid = isset($course_id) ? (int) $course_id : 0;

$CI = get_instance();
$CI->load->model('taqdar_curriculum_model', 'tq_curric');
$CI->load->model('taqdar_quiz_model', 'tq_quiz');

$tq_course = $CI->db->select('id, title, status, thumbnail, level')
                    ->where('id', $tq_cid)->get('course')->row_array();

$tq_title = t('مقرر:') . (string) ($tq_course['title'] ?? '');
$tq_sub   = t('أقسام هذا الكورس ودروسه — والنشر بعد مراجعة الإدارة.');

$tq_outline = $CI->tq_curric->outline($tq_cid);
$tq_types   = tq_cur_types();

/* أعداد أسئلة اختبارات المقرر — استعلام واحد، فلا استعلام في حلقة على
   مقرر قد يحمل مئة درس. */
$tq_qcounts = $CI->tq_quiz->counts_for_course($tq_cid);

/* الاقتراحات المعلقة تقرأ مرة لكل الدروس: استعلام لكل صف يعني استعلاما
   في حلقة على مقرر قد يحمل مئة درس. */
$tq_pending = array();
try {
    foreach ($CI->db->where('entity', 'lesson')->where('course_id', $tq_cid)
                    ->where('status', 'pending')
                    ->get('tq_content_revisions')->result_array() as $tq_r) {
        $tq_pending[(int) $tq_r['entity_id']] = $tq_r;
    }
} catch (Throwable $e) { /* الجدول لم ينشأ بعد */ }

/* TQ-DURATION — الدروس التي تخالف مشغلات الطلاب مدتها المكتوبة.
   استعلام واحد للمقرر كله، والمدة أساس القفل: خطؤها يظهر عند الطالب
   لا عند من كتبه، فيقال هنا حيث يصلح. */
$tq_durflags = $CI->tq_curric->duration_conflicts($tq_cid);

/** كل الأقسام في قائمة واحدة — لمنتقي القسم في نافذة الدرس. */
$tq_sections = $tq_outline['sections'];

/** الدرس المطلوب تحريره — من الرابط، ويفتح نافذته تلقائيا. */
$tq_edit_id = (int) $this->input->get('lesson', true);
$tq_edit    = $tq_edit_id > 0 ? $CI->tq_curric->lesson($tq_edit_id) : null;
if ($tq_edit && (int) $tq_edit['course_id'] !== $tq_cid) $tq_edit = null;
$tq_edit_objectives = $tq_edit ? $CI->tq_curric->objectives_of($tq_edit_id) : array();

/** القسم المطلوب تحريره. */
$tq_edit_sec_id = (int) $this->input->get('section', true);
$tq_edit_sec    = null;
foreach ($tq_sections as $tq_s) {
    if ((int) $tq_s['id'] === $tq_edit_sec_id) { $tq_edit_sec = $tq_s; break; }
}

$tq_counts_total = count($tq_outline['orphans']);
foreach ($tq_sections as $tq_s) $tq_counts_total += count($tq_s['items']);

include 'portal_open.php';
tq_cur_styles();
?>

<style>
.tqc-sec        { border: 1px solid var(--tq-line); border-radius: var(--tq-radius);
                  background: var(--tq-surface); margin-block-end: var(--tq-space-l); overflow: hidden; }
.tqc-sec__head  { display: flex; gap: var(--tq-space-m); align-items: center;
                  padding: var(--tq-space-l); border-block-end: 1px solid var(--tq-line); }
.tqc-sec__t     { flex: 1; min-inline-size: 0; }
.tqc-sec__t h2  { font: var(--tq-type-h3); margin: 0; }
.tqc-acts       { display: flex; gap: var(--tq-space-xs); flex-wrap: wrap; }

.tqc-les        { display: flex; gap: var(--tq-space-m); align-items: center;
                  padding: var(--tq-space-m) var(--tq-space-l);
                  border-block-end: 1px solid var(--tq-line); }
.tqc-les:last-child { border-block-end: 0; }
.tqc-les__n     { flex: none; inline-size: 28px; text-align: center;
                  font: var(--tq-type-caption); color: var(--tq-text3); font-variant-numeric: tabular-nums; }
.tqc-les__ic    { flex: none; color: var(--tq-teal); display: grid; place-items: center; }
.tqc-les__b     { flex: 1; min-inline-size: 0; }
.tqc-les__b b   { display: block; font-size: .95rem; }
.tqc-les__m     { font: var(--tq-type-micro); color: var(--tq-text2);
                  display: flex; flex-wrap: wrap; gap: var(--tq-space-s); margin-block-start: 2px; }
.tqc-les__m span::after { content: '·'; margin-inline-start: var(--tq-space-s); color: var(--tq-line); }
.tqc-les__m span:last-child::after { content: ''; }

.tqc-why        { margin: 0 var(--tq-space-l) var(--tq-space-m); padding: var(--tq-space-m);
                  border-radius: var(--tq-radius-small); background: var(--tq-dangerSoft);
                  color: var(--tq-danger); font: var(--tq-type-caption); }
.tqc-wait       { margin: 0 var(--tq-space-l) var(--tq-space-m); padding: var(--tq-space-m);
                  border-radius: var(--tq-radius-small); background: var(--tq-amberSoft);
                  color: var(--tq-text); font: var(--tq-type-caption); }

/* النافذة الأصلية — بلا مكتبة، وتغلق بـEsc وحدها. */
dialog.tqc-dlg  { border: 0; border-radius: var(--tq-radius); padding: 0;
                  inline-size: min(680px, 94vw); max-block-size: 92vh;
                  background: var(--tq-surface); color: var(--tq-text); }
dialog.tqc-dlg::backdrop { background: rgba(0,0,0,.45); }
.tqc-dlg__head  { display: flex; align-items: center; gap: var(--tq-space-m);
                  padding: var(--tq-space-l); border-block-end: 1px solid var(--tq-line);
                  position: sticky; inset-block-start: 0; background: var(--tq-surface); z-index: 1; }
.tqc-dlg__head h2 { flex: 1; font: var(--tq-type-h3); margin: 0; }
.tqc-dlg__body  { padding: var(--tq-space-l); overflow-y: auto; max-block-size: calc(92vh - 140px); }
.tqc-dlg__foot  { display: flex; gap: var(--tq-space-s); flex-wrap: wrap;
                  padding: var(--tq-space-l); border-block-start: 1px solid var(--tq-line);
                  position: sticky; inset-block-end: 0; background: var(--tq-surface); }
.tqc-x          { background: none; border: 0; cursor: pointer; color: var(--tq-text3);
                  padding: 4px; line-height: 1; }
.tqc-x:hover    { color: var(--tq-text); }
</style>

<div class="tq-row tq-row--between tq-section" style="flex-wrap:wrap;gap:var(--tq-space-m)">
    <div class="tq-row" style="gap:var(--tq-space-s);flex-wrap:wrap">
        <button class="tq-btn tq-btn--primary" type="button" data-tqc-open="secDlg" data-tqc-new>
            <?php echo tq_icon('plus', 16); ?> إضافة قسم
        </button>
        <button class="tq-btn tq-btn--secondary" type="button" data-tqc-open="lesDlg" data-tqc-new
                <?php echo $tq_sections ? '' : t('disabled aria-disabled="true" title="أضف قسما أولا"'); ?>>
            <?php echo tq_icon('plus', 16); ?> إضافة درس
        </button>
    </div>
    <div class="tq-row" style="gap:var(--tq-space-s);flex-wrap:wrap">
        <?php /* بيانات الكورس نفسه — عنوانه وصفه ومادته وحالته. ولم يكن
                 للمعلم إليها باب أصلا (TQ-COURSE-SPLIT). */ ?>
        <a class="tq-btn tq-btn--ghost" href="<?php echo base_url('teacher/course/' . $tq_cid . '/settings'); ?>">
            <?php echo tq_icon('pen', 16); ?> إعدادات الكورس
        </a>
        <a class="tq-btn tq-btn--ghost" href="<?php echo base_url('teacher/courses'); ?>">
            <?php echo tq_icon('chev-prev', 16); ?> كل كورساتي
        </a>
    </div>
</div>

<?php /* ── حالة الكورس: ما يفصل مقرره عن الطالب ─────────────────── */ ?>
<?php if ((string) ($tq_course['status'] ?? '') !== 'active'): ?>
    <p class="tqc-note tq-section">
        <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
        <span>
            <strong><?php echo t('هذا الكورس لم ينشر بعد.'); ?></strong>
            <?php echo t('ابن مقرره من الآن — والدروس التي تعتمدها الإدارة تظهر لطلابك حين ينشر الكورس.'); ?>
        </span>
    </p>
<?php endif; ?>

<?php if (empty($tq_sections) && empty($tq_outline['orphans'])): ?>

    <div class="tq-card">
        <div class="tq-empty">
            <span class="tq-icon-box tq-pastel--sky" style="inline-size:72px;block-size:72px" aria-hidden="true">
                <?php echo tq_icon('layers', 34); ?>
            </span>
            <p class="tq-empty__title"><?php echo t('لا أقسام في هذا الكورس بعد'); ?></p>
            <p class="tq-empty__text">
                <?php echo t('القسم وعاء الدروس. أضف قسما أولا — مثل «الوحدة الأولى: الأعداد» — ثم ضع دروسه فيه. والدرس لا يحفظ بلا قسم يحمله.'); ?>
            </p>
            <button class="tq-btn tq-btn--primary" type="button" data-tqc-open="secDlg" data-tqc-new>
                <?php echo tq_icon('plus', 16); ?> أضف القسم الأول
            </button>
        </div>
    </div>

<?php else: ?>

    <?php foreach ($tq_sections as $tq_i => $tq_s): ?>
    <section class="tqc-sec">
        <div class="tqc-sec__head">
            <span class="tq-icon-box tq-pastel--mint" aria-hidden="true"><?php echo tq_icon('layers', 20); ?></span>
            <div class="tqc-sec__t">
                <h2>
                    <span style="color:var(--tq-text2);font-weight:500">قسم <?php echo tq_iso($tq_i + 1); ?>:</span>
                    <?php echo html_escape($tq_s['title']); ?>
                </h2>
                <span class="tq-caption"><?php echo tq_iso(tq_lessons_word(count($tq_s['items']))); ?></span>
            </div>
            <div class="tqc-acts">
                <button class="tq-btn tq-btn--ghost tq-btn--sm" type="button"
                        data-tqc-open="secDlg" data-tqc-sec="<?php echo (int) $tq_s['id']; ?>"
                        data-tqc-title="<?php echo html_escape($tq_s['title']); ?>">
                    <?php echo tq_icon('pen', 14); ?> تعديل
                </button>
                <form method="post" action="<?php echo base_url('teacher/section/delete'); ?>"
                      data-tqc-confirm="يحذف هذا القسم و<?php echo count($tq_s['items']); ?> من دروسه، ومعها تقدم الطلاب فيها. لا رجعة.">
                    <?php echo tq_csrf(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $tq_s['id']; ?>">
                    <input type="hidden" name="course_id" value="<?php echo $tq_cid; ?>">
                    <button class="tq-btn tq-btn--ghost tq-btn--sm" type="submit">
                        <?php echo tq_icon('trash', 14); ?> حذف
                    </button>
                </form>
            </div>
        </div>

        <?php if (empty($tq_s['items'])): ?>
            <p class="tq-caption" style="padding:var(--tq-space-l)">
                <?php echo t('لا دروس في هذا القسم بعد.'); ?>
                <button class="tq-btn tq-btn--ghost tq-btn--sm" type="button"
                        data-tqc-open="lesDlg" data-tqc-new data-tqc-section="<?php echo (int) $tq_s['id']; ?>">
                    <?php echo t('أضف درسا هنا'); ?>
                </button>
            </p>
        <?php else: ?>
            <?php foreach ($tq_s['items'] as $tq_j => $tq_l):
                $tq_k    = $tq_l['tq_kind'];
                $tq_t    = $tq_types[$tq_k] ?? null;
                [$tq_tone, $tq_face] = tq_cur_status_face($tq_l['tq_status']);
                $tq_wait = isset($tq_pending[$tq_l['id']]);
            ?>
            <div class="tqc-les">
                <span class="tqc-les__n"><?php echo tq_iso($tq_j + 1); ?></span>
                <span class="tqc-les__ic" aria-hidden="true"><?php echo tq_icon($tq_t['icon'] ?? 'file', 18); ?></span>
                <div class="tqc-les__b">
                    <b><?php echo html_escape($tq_l['title']); ?></b>
                    <div class="tqc-les__m">
                        <span class="tq-badge tq-badge--<?php
                            echo $tq_tone === 'ok' ? 'mastered' : ($tq_tone === 'warn' ? 'due'
                               : ($tq_tone === 'danger' ? 'late' : 'idle')); ?>">
                            <?php echo html_escape($tq_face); ?>
                        </span>
                        <span><?php echo html_escape($tq_t['label'] ?? $tq_k); ?></span>
                        <?php if ($tq_l['duration_sec'] > 0): ?>
                            <span class="tq-ltr" dir="ltr"><?php echo html_escape($tq_l['duration']); ?></span>
                        <?php endif; ?>
                        <span><?php echo tq_iso($tq_l['objectives']); ?> هدفا</span>
                        <?php if ((int) $tq_l['is_free'] === 1): ?><span><?php echo t('معاينة مجانية'); ?></span><?php endif; ?>
                    </div>
                </div>
                <div class="tqc-acts">
                    <?php /* الاختبار بجوار درسه لا في شاشة بعيدة: هو الذي
                             يفتح الدرس التالي، فموضعه حيث يبنى الدرس.
                             والعدد يظهر في الزر — «الاختبار» وحدها لا
                             تقول أفيه أسئلة أم لا. والأعداد كلها قرئت
                             باستعلام واحد أعلى الصفحة لا باستعلام لكل صف. */
                          $tq_qn = $tq_qcounts[$tq_l['id']] ?? 0; ?>
                    <a class="tq-btn <?php echo $tq_qn > 0 ? 'tq-btn--ghost' : 'tq-btn--secondary'; ?> tq-btn--sm"
                       href="<?php echo base_url('teacher/quiz/' . (int) $tq_l['id']); ?>">
                        <?php echo tq_icon('help', 14); ?>
                        <?php echo $tq_qn > 0 ? t('الاختبار (') . tq_iso($tq_qn) . ')' : t('أضف اختبارا'); ?>
                    </a>
                    <a class="tq-btn tq-btn--ghost tq-btn--sm"
                       href="<?php echo base_url('teacher/course/' . $tq_cid) . '?lesson=' . (int) $tq_l['id']; ?>">
                        <?php echo tq_icon('pen', 14); ?> تعديل
                    </a>
                    <form method="post" action="<?php echo base_url('teacher/lesson/delete'); ?>"
                          data-tqc-confirm="يحذف هذا الدرس وأهدافه وأسئلته وتقدم الطلاب فيه. لا رجعة.">
                        <?php echo tq_csrf(); ?>
                        <input type="hidden" name="id" value="<?php echo (int) $tq_l['id']; ?>">
                        <input type="hidden" name="course_id" value="<?php echo $tq_cid; ?>">
                        <button class="tq-btn tq-btn--ghost tq-btn--sm" type="submit">
                            <?php echo tq_icon('trash', 14); ?>
                        </button>
                    </form>
                </div>
            </div>

            <?php if ((string) $tq_l['tq_status'] === 'rejected' && $tq_l['tq_review_note']): ?>
                <p class="tqc-why">
                    <strong><?php echo t('ردته الإدارة:'); ?></strong> <?php echo html_escape($tq_l['tq_review_note']); ?>
                </p>
            <?php endif; ?>
            <?php if ($tq_wait): ?>
                <p class="tqc-wait">
                    <strong><?php echo t('تعديلك على هذا الدرس ينتظر المراجعة.'); ?></strong>
                    <?php echo t('والدرس المنشور يعمل أمام طلابك كما هو حتى تعتمده الإدارة.'); ?>
                </p>
            <?php endif; ?>
            <?php if (isset($tq_durflags[$tq_l['id']])): ?>
                <div style="margin:0 var(--tq-space-l) var(--tq-space-m)">
                    <?php tq_cur_duration_flag($tq_durflags[$tq_l['id']], 'tq'); ?>
                </div>
            <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
    <?php endforeach; ?>

    <?php /* ── دروس بلا قسم: تعرض ولا تخفى ───────────────────────── */ ?>
    <?php if ($tq_outline['orphans']): ?>
    <section class="tqc-sec">
        <div class="tqc-sec__head">
            <span class="tq-icon-box tq-pastel--peach" aria-hidden="true"><?php echo tq_icon('alert', 20); ?></span>
            <div class="tqc-sec__t">
                <h2><?php echo t('دروس بلا قسم'); ?></h2>
                <span class="tq-caption">
                    <?php echo t('لا تظهر للطالب في المقرر لأن ترتيبه بالأقسام. انقل كلا منها إلى قسم.'); ?>
                </span>
            </div>
        </div>
        <?php foreach ($tq_outline['orphans'] as $tq_l): ?>
        <div class="tqc-les">
            <span class="tqc-les__ic" aria-hidden="true"><?php echo tq_icon('file', 18); ?></span>
            <div class="tqc-les__b"><b><?php echo html_escape($tq_l['title']); ?></b></div>
            <form class="tq-row" style="gap:var(--tq-space-xs)" method="post"
                  action="<?php echo base_url('teacher/lesson/move'); ?>">
                <?php echo tq_csrf(); ?>
                <input type="hidden" name="id" value="<?php echo (int) $tq_l['id']; ?>">
                <input type="hidden" name="course_id" value="<?php echo $tq_cid; ?>">
                <label class="sr-only" for="mv<?php echo (int) $tq_l['id']; ?>"><?php echo t('القسم'); ?></label>
                <select class="tq-select" id="mv<?php echo (int) $tq_l['id']; ?>" name="section_id" required>
                    <?php foreach ($tq_sections as $tq_s): ?>
                        <option value="<?php echo (int) $tq_s['id']; ?>"><?php echo html_escape($tq_s['title']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="tq-btn tq-btn--secondary tq-btn--sm" type="submit"><?php echo t('انقل'); ?></button>
            </form>
        </div>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

<?php endif; ?>

<?php /* ═════ نافذة القسم ═════════════════════════════════════════ */ ?>
<dialog class="tqc-dlg" id="secDlg">
    <form method="post" action="<?php echo base_url('teacher/section/save'); ?>">
        <?php echo tq_csrf(); ?>
        <input type="hidden" name="course_id" value="<?php echo $tq_cid; ?>">
        <input type="hidden" name="id" value="<?php echo $tq_edit_sec ? (int) $tq_edit_sec['id'] : 0; ?>"
               data-tqc-sec-id>

        <div class="tqc-dlg__head">
            <h2 data-tqc-sec-h><?php echo $tq_edit_sec ? t('تعديل القسم') : t('إضافة قسم'); ?></h2>
            <button class="tqc-x" type="button" data-tqc-close aria-label="<?php echo te('إغلاق'); ?>"><?php echo tq_icon('close', 20); ?></button>
        </div>

        <div class="tqc-dlg__body">
            <div class="tq-field">
                <label class="tq-field__label" for="sec_title">
                    <?php echo t('عنوان القسم'); ?> <span class="tqc-req" aria-hidden="true">*</span>
                </label>
                <input class="tq-input" type="text" id="sec_title" name="title" required maxlength="190"
                       value="<?php echo html_escape($tq_edit_sec['title'] ?? ''); ?>"
                       data-tqc-sec-title placeholder="<?php echo te('مثال: الوحدة الأولى — الأعداد'); ?>">
                <span class="tq-caption">
                    <?php echo t('القسم وعاء الدروس، ويظهر عنوانه للطالب في صفحة الكورس.'); ?>
                </span>
            </div>
        </div>

        <div class="tqc-dlg__foot">
            <button class="tq-btn tq-btn--primary" type="submit">
                <?php echo tq_icon('check', 16); ?> احفظ القسم
            </button>
            <button class="tq-btn tq-btn--ghost" type="button" data-tqc-close><?php echo t('إغلاق'); ?></button>
        </div>
    </form>
</dialog>

<?php /* ═════ نافذة الدرس ═════════════════════════════════════════ */ ?>
<?php
$tq_kind_now = $tq_edit ? $tq_edit['tq_kind'] : 'youtube';
if (!isset($tq_types[$tq_kind_now])) $tq_kind_now = 'youtube';
$tq_live_edit = $tq_edit && (string) $tq_edit['tq_status'] === 'published';
?>
<dialog class="tqc-dlg" id="lesDlg">
    <form method="post" enctype="multipart/form-data"
          action="<?php echo base_url('teacher/lesson/save'); ?>">
        <?php echo tq_csrf(); ?>
        <input type="hidden" name="course_id" value="<?php echo $tq_cid; ?>">
        <input type="hidden" name="id" value="<?php echo $tq_edit ? (int) $tq_edit['id'] : 0; ?>">
        <input type="hidden" name="action" value="draft" data-tqc-action>

        <div class="tqc-dlg__head">
            <h2><?php echo $tq_edit ? t('تعديل الدرس') : t('إضافة درس'); ?></h2>
            <button class="tqc-x" type="button" data-tqc-close aria-label="<?php echo te('إغلاق'); ?>"><?php echo tq_icon('close', 20); ?></button>
        </div>

        <div class="tqc-dlg__body">

            <?php if ($tq_live_edit): ?>
                <p class="tqc-note">
                    <span aria-hidden="true"><?php echo tq_icon('shield', 18); ?></span>
                    <span>
                        <strong><?php echo t('هذا الدرس منشور.'); ?></strong>
                        <?php echo t('تعديلك يرسل للمراجعة، و'); ?><strong><?php echo t('الدرس الحالي يبقى يعمل أمام طلابك'); ?></strong>
                        <?php echo t('حتى تعتمد الإدارة التعديل — فلا ينقطع عنهم شيء.'); ?>
                    </span>
                </p>
            <?php endif; ?>

            <?php /* ── النوع ─────────────────────────────────────── */ ?>
            <fieldset style="border:0;padding:0;margin:0 0 var(--tq-space-l)">
                <legend class="tq-field__label" style="padding:0"><?php echo t('نوع الدرس'); ?></legend>
                <?php tq_cur_type_picker($tq_kind_now, 'tq'); ?>
            </fieldset>

            <div class="tq-field">
                <label class="tq-field__label" for="les_title">
                    <?php echo t('عنوان الدرس'); ?> <span class="tqc-req" aria-hidden="true">*</span>
                </label>
                <input class="tq-input" type="text" id="les_title" name="title" required
                       minlength="3" maxlength="190"
                       value="<?php echo html_escape($tq_edit['title'] ?? ''); ?>">
            </div>

            <div class="tq-field">
                <label class="tq-field__label" for="les_section">
                    <?php echo t('القسم'); ?> <span class="tqc-req" aria-hidden="true">*</span>
                </label>
                <select class="tq-select" id="les_section" name="section_id" required data-tqc-les-section>
                    <?php foreach ($tq_sections as $tq_s): ?>
                        <option value="<?php echo (int) $tq_s['id']; ?>"
                            <?php echo $tq_edit && (int) $tq_edit['section_id'] === (int) $tq_s['id'] ? 'selected' : ''; ?>>
                            <?php echo html_escape($tq_s['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php /* ── حقول النوع: لوح لكل نوع، والمعروض واحد ────────
                     كلها تطبع في الصفحة ويخفى غير المختار. والسبب أن
                     التبديل بجلب من الخادم يفقد ما كتب في الحقول
                     المشتركة، ويعطل الشاشة إن تعثر النداء. */ ?>
            <?php foreach ($tq_types as $tq_k => $tq_spec):
                $tq_on = ($tq_k === $tq_kind_now); ?>
                <div data-tqc-pane="<?php echo html_escape($tq_k); ?>" <?php echo $tq_on ? '' : 'hidden'; ?>>
                    <?php tq_cur_track_note($tq_spec, 'tq'); ?>
                    <?php tq_cur_fields($tq_spec, $tq_on && $tq_edit ? $tq_edit : array(), 'tq'); ?>
                </div>
            <?php endforeach; ?>

            <div class="tq-field">
                <label class="tq-field__label" for="les_summary"><?php echo t('ملخص الدرس'); ?></label>
                <textarea class="tq-textarea" id="les_summary" name="summary" rows="3"
                          placeholder="<?php echo te('سطران يخبران الطالب بما سيخرج به من هذا الدرس'); ?>"><?php
                    echo html_escape($tq_edit['summary'] ?? ''); ?></textarea>
            </div>

            <?php /* ── الأهداف: بها تحكم بوابة الإتقان ──────────────── */ ?>
            <fieldset style="border:0;padding:0;margin:0 0 var(--tq-space-l)">
                <legend class="tq-field__label" style="padding:0"><?php echo t('الأهداف التعليمية'); ?></legend>
                <p class="tq-caption" style="margin-block-end:var(--tq-space-m)">
                    <?php echo t('من هدف إلى ثلاثة. وإليها تنسب أسئلة الاختبار، وبها يعرف النظام أي مفهوم تعثر فيه الطالب فيعيده إلى ثانيته. والهدف يكتب بفعل يقاس: «يحلل» · «يحسب» · «يميز» — لا «يفهم».'); ?>
                </p>
                <?php for ($tq_o = 0; $tq_o < 3; $tq_o++):
                    $tq_ov = $tq_edit_objectives[$tq_o] ?? null; ?>
                    <div class="tq-row" style="gap:var(--tq-space-s);margin-block-end:var(--tq-space-s)">
                        <input class="tq-input" type="text" name="objectives[]" maxlength="500"
                               style="flex:1"
                               value="<?php echo html_escape($tq_ov['text'] ?? ''); ?>"
                               placeholder="<?php echo $tq_o === 0
                                   ? t('الهدف الأول (مطلوب) — مثال: أن يحلل الطالب المقدار الثلاثي إلى عاملين')
                                   : t('هدف إضافي (اختياري)'); ?>">
                        <label class="sr-only" for="oat<?php echo $tq_o; ?>"><?php echo t('لحظة شرحه (ثانية)'); ?></label>
                        <input class="tq-input tq-ltr" type="number" min="0" id="oat<?php echo $tq_o; ?>"
                               name="objective_at[]" dir="ltr" style="inline-size:110px"
                               value="<?php echo (int) ($tq_ov['at_second'] ?? 0); ?>"
                               title="<?php echo te('الثانية التي يشرح فيها هذا الهدف في الفيديو'); ?>">
                    </div>
                <?php endfor; ?>
            </fieldset>

            <label class="tq-row" style="gap:var(--tq-space-s);cursor:pointer">
                <input type="checkbox" name="is_free" value="1"
                    <?php echo $tq_edit && (int) $tq_edit['is_free'] === 1 ? 'checked' : ''; ?>>
                <span class="tq-caption"><?php echo t('درس معاينة مجاني — يفتح لغير المشتركين. واحد أو اثنان يكفيان.'); ?></span>
            </label>
        </div>

        <div class="tqc-dlg__foot">
            <button class="tq-btn tq-btn--primary" type="submit" value="review" data-tqc-submit="review">
                <?php echo tq_icon('send', 16); ?> احفظ وأرسل للمراجعة
            </button>
            <button class="tq-btn tq-btn--secondary" type="submit" value="draft" data-tqc-submit="draft">
                <?php echo t('احفظ كمسودة'); ?>
            </button>
            <button class="tq-btn tq-btn--ghost" type="button" data-tqc-close><?php echo t('إغلاق'); ?></button>
        </div>
    </form>
</dialog>

<script>
(function () {
    'use strict';

    /* ---- النوافذ ---------------------------------------------------
       `<dialog>` أصلية: تغلق بـEsc وتحبس التركيز بلا سطر منا. والمتصفح
       الذي لا يعرفها يبقيها ظاهرة في الصفحة — وهو تدهور مقبول: النموذج
       يعمل، وشكله وحده يتأخر. */
    var byId = function (id) { return document.getElementById(id); };

    function open(dlg) {
        if (!dlg) return;
        if (typeof dlg.showModal === 'function') dlg.showModal();
        else dlg.setAttribute('open', 'open');
    }
    function close(dlg) {
        if (!dlg) return;
        if (typeof dlg.close === 'function') dlg.close();
        else dlg.removeAttribute('open');
    }

    document.querySelectorAll('[data-tqc-open]').forEach(function (b) {
        b.addEventListener('click', function () {
            var dlg = byId(b.getAttribute('data-tqc-open'));
            if (!dlg) return;

            /* تعديل قسم: العنوان والمعرف يحقنان قبل الفتح، فنافذة واحدة
               تخدم الإضافة والتعديل — ونافذتان تعنيان نموذجين يفترقان. */
            if (b.hasAttribute('data-tqc-sec')) {
                var idIn = dlg.querySelector('[data-tqc-sec-id]');
                var tiIn = dlg.querySelector('[data-tqc-sec-title]');
                var head = dlg.querySelector('[data-tqc-sec-h]');
                if (idIn) idIn.value = b.getAttribute('data-tqc-sec');
                if (tiIn) tiIn.value = b.getAttribute('data-tqc-title') || '';
                if (head) head.textContent = 'تعديل القسم';
            } else if (b.hasAttribute('data-tqc-new')) {
                var idIn2 = dlg.querySelector('[data-tqc-sec-id]');
                var tiIn2 = dlg.querySelector('[data-tqc-sec-title]');
                var head2 = dlg.querySelector('[data-tqc-sec-h]');
                if (idIn2) idIn2.value = '0';
                if (tiIn2) tiIn2.value = '';
                if (head2) head2.textContent = 'إضافة قسم';
            }

            /* «أضف درسا هنا» يختار القسم مسبقا: من ضغط داخل قسم يقصده. */
            var pre = b.getAttribute('data-tqc-section');
            if (pre) {
                var sel = dlg.querySelector('[data-tqc-les-section]');
                if (sel) sel.value = pre;
            }

            open(dlg);
        });
    });

    document.querySelectorAll('[data-tqc-close]').forEach(function (b) {
        b.addEventListener('click', function () { close(b.closest('dialog')); });
    });

    /* تبديل لوح النوع وحمل الزر لنيته: في
       `views/components/tq_lesson_panes.php` — تشترك فيه هذه الشاشة
       وشاشة «رفع الدروس»، فلا يفترق سلوكهما عند أول تعديل. */

    /* ---- الحذف يؤكد ----
       والتأكيد في المتصفح **زينة**: الخادم يرفض GET ويفحص الملكية. وهو
       هنا لأن الحذف لا رجعة فيه ويأخذ معه تقدم طلاب. */
    document.querySelectorAll('[data-tqc-confirm]').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            if (!confirm(f.getAttribute('data-tqc-confirm'))) e.preventDefault();
        });
    });

    /* ---- تحرير درس من الرابط يفتح نافذته ---- */
    <?php if ($tq_edit): ?>open(byId('lesDlg'));<?php endif; ?>
    <?php if ($tq_edit_sec): ?>open(byId('secDlg'));<?php endif; ?>
})();
</script>

<?php $this->load->view('components/tq_lesson_panes'); ?>

<?php include 'portal_close.php'; ?>
