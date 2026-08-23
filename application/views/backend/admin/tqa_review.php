<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * مراجعة المحتوى — ما ينتظر قرار الإدارة من محتوى المعلمين.
 *
 * الشاشة الغائبة التي كانت تجعل «أرسل للمراجعة» وعدا لا يتحقق: العمود
 * `lesson.tq_status = 'review'` يكتب منذ كتب نموذج الرفع، ولا شيء في
 * اللوحة يقرؤه — لا بند ولا شارة ولا استعلام واحد.
 *
 * وثلاثة قرارات في بنائها:
 *
 * ١ — **صف واحد لنوعين.** درس جديد ينتظر نشرا، وتعديل على منشور ينتظر
 *     تطبيقا. وهما سؤال واحد على المسؤول، ففصلهما في تبويبين يجعل
 *     الثاني يفتح إن تذكره أحد.
 * ٢ — **الرفض يطلب سببه في النموذج نفسه.** لا نافذة ثانية ولا صفحة:
 *     السبب حقل بجوار الزر، والزر معطل حتى يكتب. وبلا السبب يعود الدرس
 *     إلى صاحبه بكلمة «مرفوض» فيعيد إرساله كما هو.
 * ٣ — **التعديل يعرض فرقه لا حمولته.** المسؤول يقرر على ما تغير، وعرض
 *     الصف كاملا يجعله يبحث عن الفرق بعينه.
 *
 * وكل نموذج POST بتوكن: الاعتماد كتابة، ورابط GET يعتمد ينفذ بمجرد
 * جلبه — انظر TQ-GET-DESTROY.
 */

$tq_items   = isset($items)   ? $items   : array();
$tq_courses = isset($courses) ? $courses : array();
$tq_course  = isset($course)  ? (int) $course : 0;

/** أسماء أنواع الدروس — من الوحدة الموصوفة، لا من قائمة تكتب هنا. */
$tq_types = Taqdar_curriculum_model::lesson_types();
$tq_kind_label = function ($k) use ($tq_types) {
    return isset($tq_types[$k]) ? $tq_types[$k]['label'] : $k;
};
$tq_kind_icon = function ($k) use ($tq_types) {
    return isset($tq_types[$k]) ? $tq_types[$k]['icon'] : 'file';
};

/** الحقول التي يعرض فرقها — وما سواها ضجيج على شاشة قرار. */
$tq_diff_labels = array(
    'title'        => 'العنوان',
    'summary'      => 'الملخص',
    'video_url'    => 'رابط الفيديو',
    'audio_url'    => 'الملف الصوتي',
    'attachment'   => 'المرفق',
    'duration'     => 'المدة',
    'section_id'   => 'القسم',
    'is_free'      => 'معاينة مجانية',
    'video_type'   => 'المصدر',
);

/** «منذ» — والتاريخ الكامل في `title` لمن أراد الدقة. */
$tq_ago = function ($ts) {
    $ts = (int) $ts;
    if ($ts <= 0) return '—';
    $d = time() - $ts;
    if ($d < 3600)  return 'منذ ' . max(1, intdiv($d, 60)) . ' دقيقة';
    if ($d < 86400) return 'منذ ' . intdiv($d, 3600) . ' ساعة';
    return 'منذ ' . intdiv($d, 86400) . ' يوما';
};
?>

<style>
/* موضعي لهذه الشاشة، وكل قيمة من التوكنات. */
.tqr-item      { border: 1px solid var(--tq-line); border-radius: var(--tqa-radius);
                 background: var(--tq-surface); margin-block-end: var(--tq-space-l); overflow: hidden; }
.tqr-item__top { display: flex; gap: var(--tq-space-m); align-items: flex-start;
                 padding: var(--tq-space-l); }
.tqr-item__body{ flex: 1; min-inline-size: 0; }
.tqr-item__meta{ font: var(--tq-type-caption); color: var(--tq-text2);
                 margin-block-start: 4px; display: flex; flex-wrap: wrap; gap: var(--tq-space-s); }
.tqr-item__meta span::after { content: '·'; margin-inline-start: var(--tq-space-s); color: var(--tq-line); }
.tqr-item__meta span:last-child::after { content: ''; }
.tqr-diff      { margin: 0 var(--tq-space-l) var(--tq-space-l);
                 border: 1px dashed var(--tq-line); border-radius: var(--tq-radius-small);
                 padding: var(--tq-space-m); font: var(--tq-type-caption); }
.tqr-diff dl   { display: grid; grid-template-columns: max-content 1fr; gap: 4px var(--tq-space-m); margin: 0; }
.tqr-diff dt   { color: var(--tq-text3); }
.tqr-diff dd   { margin: 0; min-inline-size: 0; overflow-wrap: anywhere; }
.tqr-diff del  { color: var(--tq-text3); text-decoration: line-through; }
.tqr-diff ins  { color: var(--tq-navy); font-weight: 700; text-decoration: none; }
.tqr-acts      { display: flex; flex-wrap: wrap; gap: var(--tq-space-s); align-items: flex-start;
                 padding: var(--tq-space-m) var(--tq-space-l);
                 border-block-start: 1px solid var(--tq-line); background: var(--tq-ground); }
.tqr-acts form { display: flex; gap: var(--tq-space-s); align-items: flex-start; flex-wrap: wrap; }
.tqr-acts__why { min-inline-size: 240px; flex: 1; }
.tqr-note      { margin: 0 var(--tq-space-l) var(--tq-space-l); font: var(--tq-type-caption);
                 color: var(--tq-text2); }
</style>

<?php tqa_head('مراجعة المحتوى',
    'ما رفعه المعلمون وينتظر قرارك — قبل أن يصل إلى طالب.', 'shield'); ?>

<?php if ($tq_courses): ?>
<nav class="tqa-toolbar" aria-label="تصفية بالكورس">
    <a class="tqa-chip<?php echo $tq_course === 0 ? ' is-on' : ''; ?>"
       href="<?php echo site_url('taqdar_admin/review'); ?>">كل الكورسات</a>
    <?php foreach ($tq_courses as $tq_c): ?>
        <a class="tqa-chip<?php echo $tq_course === (int) $tq_c['id'] ? ' is-on' : ''; ?>"
           href="<?php echo site_url('taqdar_admin/review') . '?course=' . (int) $tq_c['id']; ?>">
            <?php echo html_escape($tq_c['title']); ?>
        </a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>

<?php if (empty($tq_items)): ?>

    <div class="tqa-card tqa-card--flush">
        <?php tqa_empty(
            'لا شيء ينتظر قرارك',
            'حين يرسل معلم درسا للمراجعة أو يعدل درسا منشورا، يظهر هنا — ويظهر عدده في الشريط الجانبي.',
            'الكورسات', site_url('admin/courses'), 'check-badge'
        ); ?>
    </div>

<?php else: ?>

    <p class="tqa-note tqa-section">
        <span aria-hidden="true"><?php echo tq_icon('help', 18); ?></span>
        <span>
            <strong><span class="tqa-num"><?php echo count($tq_items); ?></span> عنصرا ينتظر.</strong>
            <span style="display:block">
                <strong>درس جديد</strong> لا يراه طالب حتى تعتمده.
                و<strong>تعديل على منشور</strong> لا يمس ما يراه الطالب الآن — الدرس القديم يعمل حتى تطبق التعديل.
            </span>
        </span>
    </p>

    <?php foreach ($tq_items as $tq_it):
        $tq_new  = $tq_it['kind'] === 'new';
        $tq_live = site_url('admin/course_form/course_edit/' . (int) $tq_it['course_id']) . '?tab=curriculum';
    ?>
    <article class="tqr-item">

        <div class="tqr-item__top">
            <span class="tqa-iconbox <?php echo $tq_new ? "tqa-mint" : "tqa-peach"; ?>" aria-hidden="true">
                <?php echo tq_icon($tq_kind_icon($tq_it['tq_kind']), 20); ?>
            </span>

            <div class="tqr-item__body">
                <h2 style="font:var(--tq-type-h2)"><?php echo html_escape($tq_it['title']); ?></h2>
                <div class="tqr-item__meta">
                    <span class="tqa-badge <?php echo $tq_new ? 'tqa-badge--ok' : 'tqa-badge--warn'; ?>">
                        <?php echo $tq_new ? 'درس جديد' : 'تعديل على منشور'; ?>
                    </span>
                    <span><?php echo html_escape($tq_kind_label($tq_it['tq_kind'])); ?></span>
                    <?php if ($tq_it['course'] !== ''): ?>
                        <span><?php echo html_escape($tq_it['course']); ?></span>
                    <?php endif; ?>
                    <?php if ($tq_it['section'] !== ''): ?>
                        <span>قسم: <?php echo html_escape($tq_it['section']); ?></span>
                    <?php endif; ?>
                    <?php if ($tq_it['author'] !== ''): ?>
                        <span>المعلم: <?php echo html_escape($tq_it['author']); ?></span>
                    <?php endif; ?>
                    <span title="<?php echo html_escape(date('Y-m-d H:i', (int) $tq_it['at'])); ?>">
                        <?php echo html_escape($tq_ago($tq_it['at'])); ?>
                    </span>
                </div>
            </div>

            <a class="tqa-btn tqa-btn--ghost tqa-btn--sm" href="<?php echo $tq_live; ?>">
                <?php echo tq_icon('external', 14); ?> افتح المقرر
            </a>
        </div>

        <?php /* ── الدرس الجديد: ما ينقصه قبل أن يعتمد ─────────────── */ ?>
        <?php if ($tq_new && (int) $tq_it['objectives'] === 0): ?>
            <p class="tqa-note tqa-note--warn" style="margin:0 var(--tq-space-l) var(--tq-space-l)">
                <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
                <span>
                    <strong>هذا الدرس بلا أهداف.</strong>
                    وبوابة الإتقان تحكم بالأهداف وأسئلتها — فدرس بلا هدف يبقى الدرس الذي بعده مقفلا،
                    ولا سبيل إلى فتحه. اعتمده إن شئت، والأولى أن يرده صاحبه بهدف.
                </span>
            </p>
        <?php endif; ?>

        <?php /* ── التعديل: فرقه لا حمولته ──────────────────────────── */ ?>
        <?php if (!$tq_new && !empty($tq_it['payload'])):
            $tq_before = $this->db->where('id', (int) $tq_it['entity_id'])->get('lesson')->row_array() ?: array();
            $tq_rows   = array();
            foreach ($tq_diff_labels as $tq_col => $tq_lbl) {
                if (!array_key_exists($tq_col, $tq_it['payload'])) continue;
                $tq_old = (string) ($tq_before[$tq_col] ?? '');
                $tq_now = (string) $tq_it['payload'][$tq_col];
                if ($tq_old === $tq_now) continue;
                $tq_rows[$tq_lbl] = array($tq_old, $tq_now);
            }
        ?>
            <div class="tqr-diff">
                <?php if (empty($tq_rows)): ?>
                    <p style="margin:0;color:var(--tq-text3)">لا فرق ظاهرا في الحقول الرئيسية — قد يكون التعديل في الأهداف أو المرفقات.</p>
                <?php else: ?>
                    <dl>
                    <?php foreach ($tq_rows as $tq_lbl => [$tq_old, $tq_now]): ?>
                        <dt><?php echo html_escape($tq_lbl); ?></dt>
                        <dd>
                            <?php if ($tq_old !== ''): ?>
                                <del><?php echo html_escape(mb_substr($tq_old, 0, 160)); ?></del><br>
                            <?php endif; ?>
                            <ins><?php echo html_escape(mb_substr($tq_now, 0, 160)); ?></ins>
                        </dd>
                    <?php endforeach; ?>
                    </dl>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (trim((string) $tq_it['note']) !== ''): ?>
            <p class="tqr-note">
                <strong>يقول المعلم:</strong> <?php echo html_escape($tq_it['note']); ?>
            </p>
        <?php endif; ?>

        <div class="tqr-acts">
            <form method="post" action="<?php echo site_url('taqdar_admin/review_decide'); ?>">
                <?php echo tq_csrf(); ?>
                <input type="hidden" name="decision"    value="approve">
                <input type="hidden" name="entity"      value="<?php echo html_escape($tq_it['entity']); ?>">
                <input type="hidden" name="entity_id"   value="<?php echo (int) $tq_it['entity_id']; ?>">
                <input type="hidden" name="revision_id" value="<?php echo (int) $tq_it['revision_id']; ?>">
                <input type="hidden" name="course"      value="<?php echo $tq_course; ?>">
                <button class="tqa-btn tqa-btn--primary tqa-btn--sm" type="submit">
                    <?php echo tq_icon('check-badge', 14); ?>
                    <?php echo $tq_new ? 'اعتمد وانشر' : 'طبق التعديل'; ?>
                </button>
            </form>

            <form method="post" action="<?php echo site_url('taqdar_admin/review_decide'); ?>"
                  data-tqr-reject>
                <?php echo tq_csrf(); ?>
                <input type="hidden" name="decision"    value="reject">
                <input type="hidden" name="entity"      value="<?php echo html_escape($tq_it['entity']); ?>">
                <input type="hidden" name="entity_id"   value="<?php echo (int) $tq_it['entity_id']; ?>">
                <input type="hidden" name="revision_id" value="<?php echo (int) $tq_it['revision_id']; ?>">
                <input type="hidden" name="course"      value="<?php echo $tq_course; ?>">

                <label class="sr-only" for="why-<?php echo (int) $tq_it['entity_id']; ?>-<?php echo (int) $tq_it['revision_id']; ?>">
                    سبب الرد
                </label>
                <input class="tqa-input tqr-acts__why"
                       id="why-<?php echo (int) $tq_it['entity_id']; ?>-<?php echo (int) $tq_it['revision_id']; ?>"
                       type="text" name="reason" maxlength="500" required minlength="5"
                       placeholder="سبب الرد — يقرؤه المعلم ويصلح عليه">
                <button class="tqa-btn tqa-btn--danger tqa-btn--sm" type="submit">
                    <?php echo tq_icon('close', 14); ?> رد إلى المعلم
                </button>
            </form>
        </div>
    </article>
    <?php endforeach; ?>

<?php endif; ?>

<script>
/* الرد يؤكد مرة: قرار يعود إلى إنسان ينتظره، والضغطة الخاطئة تكلفه يوما.
   والتحقق من السبب في `required` و`minlength` — والخادم يفحصه ثانية:
   نموذج يتخطى بلا جافاسكربت لا يفتح بابا. */
document.querySelectorAll('[data-tqr-reject]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
        if (!f.reportValidity()) { e.preventDefault(); return; }
        if (!confirm('يرد هذا العنصر إلى المعلم مع السبب. أتؤكد؟')) e.preventDefault();
    });
});
</script>
