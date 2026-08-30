<?php
/**
 * بوابة المعلم — إعدادات الكورس (إنشاء وتعديل).
 *
 * TQ-COURSE-SPLIT — الشاشة التي لم تكن.
 *
 * كان المعلم يملك نموذج **إنشاء** في `teacher/courses` بأربعة حقول
 * (عنوان ومستوى ووصفين)، ولا يملك شاشة **تعديل** أصلا. فمن أخطأ عنوان
 * كورسه لم يصححه، ومن كتب وصفه ناقصا لم يتمه، ومن أراد صورة لم يضعها.
 * واللوحة تحرر الكورس نفسه بتسعة تبويبات.
 *
 * والأثقل من ذلك أن **الصف والمادة** لم يكونا في النموذج. والكتالوج
 * ومحرك الاشتراكات لا يقرآن `course` في سطر واحد — يقرآن `paths` وحده
 * (انظر [Taqdar_course_link_model.php]). فكل كورس أنشأه معلم منذ اليوم
 * الأول ولد محجوبا: لا يظهر في «المواد والبرامج»، ولا تفتحه باقة، ولا
 * يصل إليه طالب. ولا شيء في شاشته يقول لماذا.
 *
 * والحقول هنا **لا تكتب**: تطبع من
 * `Taqdar_curriculum_model::course_fields()` — الوصف الواحد الذي تعرضه
 * اللوحة والبوابة معا. فحقل يضاف غدا يظهر في الشاشتين، ولا تفترق واحدة
 * عن أختها كما افترقتا حتى اليوم.
 */

$tq_nav   = 'courses';
$tq_role  = 'teacher';
$tq_icon  = 'book';

$CI  = get_instance();
$CI->load->model('taqdar_curriculum_model', 'tq_curric');
$CI->load->model('taqdar_course_link_model', 'tq_link_m');

$tq_cid   = (int) ($course_id ?? 0);
$tq_uid   = (int) $this->session->userdata('user_id');
$tq_actor = $CI->tq_curric->actor_as('teacher', $tq_uid);
$tq_spec  = $CI->tq_curric->course_fields($tq_actor);
$tq_row   = $tq_cid > 0 ? ($CI->tq_curric->course($tq_cid) ?: array()) : array();

if ($tq_cid > 0 && !$tq_row) {
    $tq_title = t('كورس غير موجود');
    $tq_sub   = '';
    include 'portal_open.php';
    echo '<div class="tq-card"><div class="tq-empty">'
       . t('<p class="tq-empty__title">لا كورس بهذا المعرف</p>')
       . '<a class="tq-btn tq-btn--primary" href="' . base_url('teacher/courses') . t('">كل كورساتي</a>')
       . '</div></div>';
    include 'portal_close.php';
    return;
}

$tq_new   = $tq_cid <= 0;
$tq_title = $tq_new ? t('كورس جديد') : t('إعدادات:') . (string) ($tq_row['title'] ?? '');
$tq_sub   = $tq_new
    ? t('عرفه أولا، ثم ابن مقرره.')
    : t('بيانات هذا الكورس وما يفصله عن الطالب.');

/* هل يستطيع هذا المعلم النشر؟ الجواب يغير ما يقال عن «الحالة» — ولا
   يغير ما يعرض: النموذج هو من يحكم، والشاشة تشرح. */
$tq_may_publish = $CI->tq_curric->may_publish($tq_actor);

/* ما يفصل هذا الكورس عن الطالب — بالترتيب الذي يعالج به.
   وهي القائمة نفسها التي تعرضها اللوحة في شاشة تحرير الكورس، لأن
   السؤال واحد: «نشرته ولم أجده، لماذا؟». */
$tq_gaps = $tq_new ? array() : $CI->tq_link_m->diagnose($tq_cid);
$tq_link = $tq_new ? array('path_id' => 0, 'slug' => '') : $CI->tq_link_m->link_of($tq_cid);

include 'portal_open.php';
tq_cur_styles();   /* `tqc-grid` و`tqc-check` و`tqc-note` — مكون المنهج */
?>

<div class="tq-row tq-row--between tq-section" style="flex-wrap:wrap;gap:var(--tq-space-m)">
    <?php if (!$tq_new): ?>
        <a class="tq-btn tq-btn--secondary" href="<?php echo base_url('teacher/course/' . $tq_cid); ?>">
            <?php echo tq_icon('layers', 16); ?> مقرر هذا الكورس
        </a>
    <?php else: ?><span></span><?php endif; ?>

    <a class="tq-btn tq-btn--ghost" href="<?php echo base_url('teacher/courses'); ?>">
        <?php echo tq_icon('chev-prev', 16); ?> كل كورساتي
    </a>
</div>

<?php if ($m = $this->session->flashdata('tq_ok')): ?>
    <div class="tq-alert tq-alert--ok tq-section" role="status"><?php echo html_escape($m); ?></div>
<?php endif; ?>
<?php if ($m = $this->session->flashdata('tq_error')): ?>
    <div class="tq-alert tq-alert--no tq-section" role="alert"><?php echo html_escape($m); ?></div>
<?php endif; ?>

<?php /* ── لوح الوصول ─────────────────────────────────────────────
         «منشور» في جدول `course` لا تعني ظاهرا: الكتالوج ومحرك
         الاشتراكات يقرآن من `paths`. فمن نشر كورسه ولم يجده في الموقع
         لم يكن أمامه ما يفسر ذلك ولا موضع يصلحه منه. */ ?>
<?php if ($tq_gaps): ?>
    <div class="tq-section">
    <?php foreach ($tq_gaps as [$tq_tone, $tq_t, $tq_b, $tq_href]): ?>
        <p class="tqc-note" style="margin-block-end:var(--tq-space-s)">
            <span aria-hidden="true"><?php echo tq_icon($tq_tone === 'warn' ? 'alert' : 'help', 18); ?></span>
            <span><strong><?php echo html_escape($tq_t); ?></strong>
                  <span style="display:block"><?php echo $tq_b; ?></span></span>
        </p>
    <?php endforeach; ?>
    </div>
<?php elseif (!$tq_new && (int) $tq_link['path_id'] > 0): ?>
    <p class="tqc-note tq-section">
        <span aria-hidden="true"><?php echo tq_icon('check-badge', 18); ?></span>
        <span><strong><?php echo t('هذا الكورس يصل إلى الطالب.'); ?></strong>
              <?php echo t('منشور، وله صف ومادة، وباقة تفتحه.'); ?></span>
    </p>
<?php endif; ?>

<?php if ($tq_new): ?>
    <p class="tqc-note tq-section">
        <span aria-hidden="true"><?php echo tq_icon('help', 18); ?></span>
        <span>
            <?php if ($tq_may_publish): ?>
                عرف الكورس هنا، ثم ابن مقرره. و<strong><?php echo t('الصف والمادة'); ?></strong> هما ما يجعله
                يظهر في «المواد والبرامج» وتفتحه باقة — وبغيرهما يبقى محتوى داخليا.
            <?php else: ?>
                الكورس الجديد يبدأ <strong><?php echo t('بانتظار مراجعة الإدارة'); ?></strong><?php echo t('، وتستطيع رفع دروسه من الآن. و'); ?><strong><?php echo t('الصف والمادة'); ?></strong> هما ما يجعله يظهر في «المواد والبرامج»
                وتفتحه باقة — وبغيرهما يبقى محتوى داخليا لا يعرض في الموقع العام.
            <?php endif; ?>
        </span>
    </p>
<?php elseif (!$tq_may_publish): ?>
    <p class="tqc-note tq-section">
        <span aria-hidden="true"><?php echo tq_icon('help', 18); ?></span>
        <span>
            <strong><?php echo t('النشر قرار الإدارة.'); ?></strong>
            <?php echo t('ما تختاره «منشورا» أو «خاصا» أو «قادما» يحفظ'); ?> <strong><?php echo t('قيد المراجعة'); ?></strong><?php echo t('، وتقرره الإدارة — كما هو الحال في نشر الدروس.'); ?>
        </span>
    </p>
<?php endif; ?>

<form method="post" enctype="multipart/form-data"
      action="<?php echo base_url('teacher/course/save'); ?>">
    <?php echo tq_csrf(); ?>
    <input type="hidden" name="course_id" value="<?php echo $tq_cid; ?>">

    <?php tq_cur_course_form($tq_spec, $tq_row, 'tq'); ?>

    <div class="tq-row" style="gap:var(--tq-space-s);flex-wrap:wrap">
        <button class="tq-btn tq-btn--primary" type="submit">
            <?php echo tq_icon('check', 16); ?>
            <?php echo $tq_new ? t('أنشئ الكورس') : t('احفظ التعديل'); ?>
        </button>
        <a class="tq-btn tq-btn--ghost"
           href="<?php echo base_url($tq_new ? 'teacher/courses' : 'teacher/course/' . $tq_cid); ?>"><?php echo t('إلغاء'); ?></a>
    </div>
</form>

<?php include 'portal_close.php'; ?>
