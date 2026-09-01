<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * الشهادات.
 *
 * القاعدة الحاكمة: **لا تصدر شهادة على مشاهدة، بل على إتقان مقاس.**
 * ولذلك لا تبنى هذه الشاشة من نسبة المشاهدة في watch_histories — الشهادة
 * تنتظر إتمام البرنامج واجتياز الامتحان النهائي، وكلاهما يقرأ mastered_at
 * من lesson_progress وattempts. وحتى يوجد محتوى بأهداف وبوابات:
 * الحالة الفارغة الصحيحة، لا شهادة مبنية على وقت تشغيل.
 *
 * كل شهادة تحمل رمز تحقق يفتح صفحة عامة تؤكد صحتها.
 */
include 'tq_student_styles.php';
include 'tq_student_data.php';

$tq_uid = isset($user_id) ? (int) $user_id : tq_s_uid();
if (!isset($tq_counts)) $tq_counts = tq_s_counts($tq_uid);

$tq_nav   = 'certificates';
$tq_role  = 'student';
$tq_title = t('الشهادات');
$tq_sub   = t('ما أتقنته، موثقا وقابلا للتحقق');
$tq_icon  = 'award';

/* القاعدة في `Taqdar_student_model` لا هنا: الواجهة تسأل السؤال نفسه،
   ونسخة ثانية من شرط «اجتاز امتحان محطة» تفترق عن أختها عند أول تعديل. */
$CI = get_instance();
$CI->load->model('taqdar_student_model', 'tq_stu');
$tq_certs = $CI->tq_stu->certificates($tq_uid);

include 'portal_open.php';
?>

<div class="tq-cols">
    <div>
        <?php if (empty($tq_certs)): ?>
            <div class="tq-card">
                <div class="tq-empty">
                    <span class="tq-icon-box tq-pastel--mint" style="inline-size:72px;block-size:72px" aria-hidden="true">
                        <?php echo tq_icon('award', 36); ?>
                    </span>
                    <p class="tq-empty__title"><?php echo t('لا شهادات بعد'); ?></p>
                    <p class="tq-empty__text">
                        <?php echo t('الشهادة تصدر على إتقان مقاس لا على مشاهدة: تنهي المحطة، وتجتاز اختبارها، فتصلك شهادة بأهدافها ورمز تحقق.'); ?>
                    </p>
                    <a class="tq-btn tq-btn--primary" href="<?php echo base_url('student/lessons'); ?>"><?php echo t('تابع دروسك'); ?></a>
                </div>
            </div>
        <?php else: ?>
            <div class="tq-grid tq-grid--2">
                <?php foreach ($tq_certs as $i => $c):
                    $fam = tq_pastel($i); ?>
                    <article class="tq-card tq-card--raised">
                        <div class="tq-pastel tq-pastel--<?php echo $fam; ?>" style="margin-block-end:var(--tq-space-l)">
                            <span class="tq-pastel__label tq-micro"><?php echo t('شهادة إتقان'); ?></span>
                            <h3 class="tq-pastel__title" style="margin:var(--tq-space-xs) 0 0">
                                <?php echo html_escape($c['milestone_title'] ?: $c['path_title'] ?: t('محطة')); ?>
                            </h3>
                        </div>
                        <dl class="tq-s-list">
                            <div class="tq-s-row">
                                <dt class="tq-caption"><?php echo t('نسبة الإتقان'); ?></dt>
                                <dd style="margin:0"><?php echo tq_num(((int) $c['score']) . '%'); ?></dd>
                            </div>
                            <div class="tq-s-row">
                                <dt class="tq-caption"><?php echo t('تاريخ الإصدار'); ?></dt>
                                <dd style="margin:0"><?php echo tq_iso(html_escape((string) $c['submitted_at'])); ?></dd>
                            </div>
                            <div class="tq-s-row">
                                <dt class="tq-caption"><?php echo t('رمز التحقق'); ?></dt>
                                <dd style="margin:0"><?php echo tq_num($CI->tq_stu->certificate_code($c['id']), 'tq-num--sm'); ?></dd>
                            </div>
                        </dl>
                        <div class="tq-row" style="margin-block-start:var(--tq-space-l)">
<?php /* «تنزيل» بأيقونة تنزيل كانت تعد بملف ينزل، والوجهة صفحة شهادة
                                     تفتح في المتصفح — لا ملف ولا تنزيل. والاسم يقول ما يقع،
                                     وفي الصفحة نفسها زر حفظ نسخة PDF. */ ?>
                            <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo base_url('student/certificate/' . (int) $c['id']); ?>">
                                <?php echo tq_icon('award'); ?> <?php echo t('افتح الشهادة'); ?>
                            </a>
                            <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('student/verify/' . (int) $c['id']); ?>"><?php echo t('صفحة التحقق'); ?></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <aside class="tq-aside">
        <div class="tq-card">
            <h2 class="tq-card__title"><?php echo t('كيف تصدر الشهادة'); ?></h2>
            <?php /* `<li>` لا `<div>`: `<ol>` لا يقبل إلا عناصر قائمة، و`<div>` داخلها
                     يخرج من الشجرة فيقرؤها قارئ الشاشة قائمة **فارغة** بجوار ثلاث فقرات
                     سائبة — فيضيع الترتيب وهو كل معنى الخطوات. و`counter-reset` كان
                     مكتوبا بلا عداد يستعمله، فحذف. */ ?>
            <ol class="tq-s-list">
                <?php foreach ([
                    t('تنهي دروس المحطة وتتقنها واحدا واحدا'),
                    t('تجتاز اختبار المحطة'),
                    t('تصلك شهادة بأهدافك المتقنة ورمز تحقق'),
                ] as $n => $step): ?>
                    <li class="tq-s-row">
                        <span class="tq-icon-box tq-pastel--sky" aria-hidden="true"><?php echo tq_num($n + 1, 'tq-num--sm'); ?></span>
                        <span class="tq-caption"><?php echo html_escape($step); ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
            <p class="tq-micro" style="margin-block-start:var(--tq-space-l)">
                <?php echo t('رمز التحقق يفتح صفحة عامة تؤكد صحة الشهادة لأي جهة.'); ?>
            </p>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
