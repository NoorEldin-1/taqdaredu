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
$tq_types = tq_cur_types();
$tq_kind_label = function ($k) use ($tq_types) {
    return isset($tq_types[$k]) ? $tq_types[$k]['label'] : $k;
};
$tq_kind_icon = function ($k) use ($tq_types) {
    return isset($tq_types[$k]) ? $tq_types[$k]['icon'] : 'file';
};

/** الحقول التي يعرض فرقها — وما سواها ضجيج على شاشة قرار. */
$tq_diff_labels = array(
    'title'        => t('العنوان'),
    'summary'      => t('الملخص'),
    'video_url'    => t('رابط الفيديو'),
    'audio_url'    => t('الملف الصوتي'),
    'attachment'   => t('المرفق'),
    'duration'     => t('المدة'),
    'section_id'   => t('القسم'),
    'is_free'      => t('معاينة مجانية'),
    'video_type'   => t('المصدر'),
);

/** «منذ» — والتاريخ الكامل في `title` لمن أراد الدقة. */
$tq_ago = function ($ts) {
    $ts = (int) $ts;
    if ($ts <= 0) return '—';
    $d = time() - $ts;
    if ($d < 3600)  return t('منذ ') . max(1, intdiv($d, 60)) . t(' دقيقة');
    if ($d < 86400) return t('منذ ') . intdiv($d, 3600) . t(' ساعة');
    return t('منذ ') . intdiv($d, 86400) . t(' يوما');
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

<?php tqa_head(t('مراجعة المحتوى'),
    t('ما رفعه المعلمون وينتظر قرارك — قبل أن يصل إلى طالب.'), 'shield'); ?>

<?php if ($tq_courses): ?>
<nav class="tqa-toolbar" aria-label="<?php echo te('تصفية بالكورس'); ?>">
    <a class="tqa-chip<?php echo $tq_course === 0 ? ' is-on' : ''; ?>"
       href="<?php echo site_url('taqdar_admin/review'); ?>"><?php echo t('كل الكورسات'); ?></a>
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
            t('لا شيء ينتظر قرارك'),
            t('حين يرسل معلم درسا للمراجعة أو يعدل درسا منشورا، يظهر هنا — ويظهر عدده في الشريط الجانبي.'),
            t('الكورسات'), site_url('admin/courses'), 'check-badge'
        ); ?>
    </div>

<?php else: ?>

    <p class="tqa-note tqa-section">
        <span aria-hidden="true"><?php echo tq_icon('help', 18); ?></span>
        <span>
            <strong><span class="tqa-num"><?php echo count($tq_items); ?></span> <?php echo t('عنصرا ينتظر.'); ?></strong>
            <span style="display:block">
                <strong><?php echo t('كورس جديد'); ?></strong> <?php echo t('أنشأه معلم ولا يظهر في الموقع حتى تعتمده. و'); ?><strong><?php echo t('درس جديد'); ?></strong> <?php echo t('لا يراه طالب حتى تعتمده. و'); ?><strong><?php echo t('تعديل على منشور'); ?></strong> <?php echo t('لا يمس ما يراه الطالب الآن — الدرس القديم يعمل حتى تطبق التعديل.'); ?>
            </span>
        </span>
    </p>

    <?php foreach ($tq_items as $tq_it):
        /* ثلاثة أنواع لا نوعان — TQ-COURSE-REVIEW: كورس ينتظر نشرا،
           ودرس جديد ينتظر نشرا، وتعديل على منشور ينتظر تطبيقا. */
        $tq_kind_row = $tq_it['kind'];
        $tq_course_row = $tq_kind_row === 'course';
        $tq_new  = $tq_kind_row === 'new';
        $tq_live = $tq_course_row
            ? site_url('admin/course_form/course_edit/' . (int) $tq_it['course_id']) . '?tab=basic'
            : site_url('admin/course_form/course_edit/' . (int) $tq_it['course_id']) . '?tab=curriculum';
    ?>
    <article class="tqr-item">

        <div class="tqr-item__top">
            <span class="tqa-iconbox <?php echo $tq_course_row ? 'tqa-sand' : ($tq_new ? 'tqa-mint' : 'tqa-peach'); ?>" aria-hidden="true">
                <?php echo tq_icon($tq_course_row ? 'book' : $tq_kind_icon($tq_it['tq_kind']), 20); ?>
            </span>

            <div class="tqr-item__body">
                <h2 style="font:var(--tq-type-h2)"><?php echo html_escape($tq_it['title']); ?></h2>
                <div class="tqr-item__meta">
                    <span class="tqa-badge <?php echo $tq_course_row ? 'tqa-badge--warn' : ($tq_new ? 'tqa-badge--ok' : 'tqa-badge--warn'); ?>">
                        <?php echo $tq_course_row ? t('كورس جديد') : ($tq_new ? t('درس جديد') : t('تعديل على منشور')); ?>
                    </span>
                    <?php if ($tq_course_row): ?>
                        <span><?php echo tq_num((int) $tq_it['sections']); ?> <?php echo t('قسما'); ?></span>
                        <span><?php echo tq_num((int) $tq_it['lessons']); ?> <?php echo t('درسا'); ?></span>
                        <?php if ($tq_it['grade'] !== ''): ?>
                            <span><?php echo html_escape($tq_it['grade']); ?></span>
                        <?php endif; ?>
                        <?php if ($tq_it['subject'] !== ''): ?>
                            <span><?php echo html_escape($tq_it['subject']); ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span><?php echo html_escape($tq_kind_label($tq_it['tq_kind'])); ?></span>
                        <?php if ($tq_it['course'] !== ''): ?>
                            <span><?php echo html_escape($tq_it['course']); ?></span>
                        <?php endif; ?>
                        <?php if ($tq_it['section'] !== ''): ?>
                            <span><?php echo t('قسم:'); ?> <?php echo html_escape($tq_it['section']); ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($tq_it['author'] !== ''): ?>
                        <span><?php echo t('المعلم:'); ?> <?php echo html_escape($tq_it['author']); ?></span>
                    <?php endif; ?>
                    <span title="<?php echo html_escape(date('Y-m-d H:i', (int) $tq_it['at'])); ?>">
                        <?php echo html_escape($tq_ago($tq_it['at'])); ?>
                    </span>
                </div>
            </div>

            <a class="tqa-btn tqa-btn--ghost tqa-btn--sm" href="<?php echo $tq_live; ?>">
                <?php echo tq_icon('external', 14); ?>
                <?php echo $tq_course_row ? t('افتح الكورس') : t('افتح المقرر'); ?>
            </a>
        </div>

        <?php /* ── الكورس الجديد: ما يمنعه من الوصول إلى طالب ────────
                الحقلان اللذان لا يقرؤهما `course` أصلا هما اللذان يحكمان
                وصوله: الكتالوج ومحرك الاشتراكات يقرآن `paths` وحده
                (TQ-COURSE-SPLIT). فكورس بلا صف ومادة ينشر ولا يراه أحد،
                ولا شيء يقول لماذا — فيقال هنا **قبل** النشر. */ ?>
        <?php if ($tq_course_row && empty($tq_it['linked'])): ?>
            <p class="tqa-note tqa-note--warn" style="margin:0 var(--tq-space-l) var(--tq-space-l)">
                <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
                <span>
                    <strong><?php echo t('هذا الكورس بلا صف أو مادة.'); ?></strong>
                    <?php echo t('والباقة تمنح صفا ومادة لا كورسا بعينه، والكتالوج يقرأ «المواد والبرامج» لا جدول الكورسات — فنشره بحالته هذه يجعله'); ?> <strong><?php echo t('موجودا ولا يصل إليه أحد'); ?></strong><?php echo t('. أكملهما من «افتح الكورس» ثم اعتمده.'); ?>
                </span>
            </p>
        <?php elseif ($tq_course_row && (int) $tq_it['lessons'] === 0): ?>
            <p class="tqa-note tqa-note--warn" style="margin:0 var(--tq-space-l) var(--tq-space-l)">
                <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
                <span>
                    <strong><?php echo t('هذا الكورس بلا درس واحد.'); ?></strong>
                    <?php echo t('ينشر فيظهر في «المواد والبرامج» بعنوانه، ويفتحه الطالب على منهج فارغ.'); ?>
                </span>
            </p>
        <?php endif; ?>

        <?php /* ── الدرس الجديد: ما ينقصه قبل أن يعتمد ─────────────── */ ?>
        <?php if ($tq_new && (int) $tq_it['objectives'] === 0): ?>
            <p class="tqa-note tqa-note--warn" style="margin:0 var(--tq-space-l) var(--tq-space-l)">
                <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
                <span>
                    <strong><?php echo t('هذا الدرس بلا أهداف.'); ?></strong>
                    <?php echo t('وبوابة الإتقان تحكم بالأهداف وأسئلتها — فدرس بلا هدف يبقى الدرس الذي بعده مقفلا، ولا سبيل إلى فتحه. اعتمده إن شئت، والأولى أن يرده صاحبه بهدف.'); ?>
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
                    <p style="margin:0;color:var(--tq-text3)"><?php echo t('لا فرق ظاهرا في الحقول الرئيسية — قد يكون التعديل في الأهداف أو المرفقات.'); ?></p>
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
                <strong><?php echo t('يقول المعلم:'); ?></strong> <?php echo html_escape($tq_it['note']); ?>
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
                    <?php echo $tq_course_row ? t('اعتمد الكورس وانشره') : ($tq_new ? t('اعتمد وانشر') : t('طبق التعديل')); ?>
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

                <label class="sr-only" for="why-<?php echo html_escape($tq_it['entity']); ?>-<?php echo (int) $tq_it['entity_id']; ?>-<?php echo (int) $tq_it['revision_id']; ?>">
                    <?php echo t('سبب الرد'); ?>
                </label>
                <input class="tqa-input tqr-acts__why"
                       id="why-<?php echo html_escape($tq_it['entity']); ?>-<?php echo (int) $tq_it['entity_id']; ?>-<?php echo (int) $tq_it['revision_id']; ?>"
                       type="text" name="reason" maxlength="500" required minlength="5"
                       placeholder="<?php echo te('سبب الرد — يقرؤه المعلم ويصلح عليه'); ?>">
                <button class="tqa-btn tqa-btn--danger tqa-btn--sm" type="submit">
                    <?php echo tq_icon('close', 14); ?> <?php echo $tq_course_row ? t('رد الكورس') : t('رد إلى المعلم'); ?>
                </button>
            </form>
        </div>
    </article>
    <?php endforeach; ?>

<?php endif; ?>

<?php /* ── مفتاحا هذه الشاشة — TQ-REVIEW-KNOBS ────────────────────────
        الأول يحدد أيمر محتوى المعلم من هنا أصلا، والثاني نصاب تصحيح
        مدة درس كتبت خطأ. وكلاهما كان يعمل بافتراضه ولا صف له في
        `settings` ولا حقل في المستودع كله: من أراد أن يفتح النشر
        المباشر لمعلميه لم يجد أين، ومن سأل «لماذا لم تصحح مدة هذا
        الدرس؟» لم يجد النصاب مكتوبا في موضع.
        وموضعهما هنا لا في «إعدادات المنصة»: هذه هي الشاشة التي يظهر
        فيها أثرهما، ومفتاح يدفن في شاشة لا تفتح ميزة لا توجد. */ ?>
<section class="tqa-card tqa-section" style="margin-block-start:var(--tq-space-xl)">
    <h2 class="tqa-card__title" style="margin-block-end:var(--tq-space-s)"><?php echo t('إعدادات المراجعة'); ?></h2>
    <form method="post" action="<?php echo site_url('taqdar_admin/review_settings'); ?>">
        <?php echo tq_csrf(); ?>
        <input type="hidden" name="direct_sent" value="1">

        <div class="tqa-field">
            <label class="tqa-field__label">
                <input type="checkbox" name="tq_teacher_direct_publish" value="1"
                       <?php echo !empty($tq_direct_publish) ? 'checked' : ''; ?>>
                <?php echo t('النشر المباشر للمعلمين'); ?>
            </label>
            <span class="tqa-field__hint">
                <?php echo t('حين يفتح، ينشر المعلم كورسه ودروسه بنفسه ولا يمر شيء بهذه الشاشة. وحين يغلق — وهو الافتراض — يحفظ ما يعلنه منشورا «قيد المراجعة» ويقال له ذلك، وينتظر هنا.'); ?>
            </span>
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="tqw"><?php echo t('نصاب تصحيح المدة'); ?></label>
            <input class="tqa-input tq-ltr" type="number" id="tqw" name="tq_duration_witnesses"
                   dir="ltr" min="2" max="20" step="1"
                   value="<?php echo (int) (isset($tq_witnesses) ? $tq_witnesses : 2); ?>"
                   style="max-inline-size:120px">
            <span class="tqa-field__hint">
                <?php echo t('كم طالبا يجب أن تتفق مشغلاتهم — بهامش ١٠٪ — قبل أن تصحح المدة المكتوبة في صف الدرس تلقائيا. وما دون النصاب يعرض تنبيها في شاشة المقرر ولا يكتب. والحد الأدنى اثنان: بشاهد واحد يستطيع طالب يعدل جافاسكربته أن يعلن مدة عشر ثوان فيفسد رقم الدرس على زملائه كلهم.'); ?>
            </span>
        </div>

        <button class="tqa-btn tqa-btn--primary tqa-btn--sm" type="submit"><?php echo t('احفظ'); ?></button>
    </form>
</section>

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
