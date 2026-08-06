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
$tq_title = 'الشهادات';
$tq_sub   = 'ما أتقنته، موثقا وقابلا للتحقق';
$tq_icon  = 'award';

$tq_certs = [];
if ($this->db->table_exists('attempts')) {
    $tq_certs = $this->db->query(
        "SELECT a.id, a.score, a.submitted_at, p.title AS path_title, m.title AS milestone_title
           FROM attempts a
           JOIN assessments s ON s.id = a.assessment_id AND s.type = 'exam'
           LEFT JOIN milestones m ON m.id = s.milestone_id
           LEFT JOIN paths p ON p.id = COALESCE(s.path_id, m.path_id)
          WHERE a.student_id = ? AND a.passed = 1
          ORDER BY a.submitted_at DESC", [$tq_uid]
    )->result_array();
}

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
                    <p class="tq-empty__title">لا شهادات بعد</p>
                    <p class="tq-empty__text">
                        الشهادة تصدر على إتقان مقاس لا على مشاهدة: تنهي المحطة،
                        وتجتاز اختبارها، فتصلك شهادة بأهدافها ورمز تحقق.
                    </p>
                    <a class="tq-btn tq-btn--primary" href="<?php echo base_url('student/lessons'); ?>">تابع دروسك</a>
                </div>
            </div>
        <?php else: ?>
            <div class="tq-grid tq-grid--2">
                <?php foreach ($tq_certs as $i => $c):
                    $fam = tq_pastel($i); ?>
                    <article class="tq-card tq-card--raised">
                        <div class="tq-pastel tq-pastel--<?php echo $fam; ?>" style="margin-block-end:var(--tq-space-l)">
                            <span class="tq-pastel__label tq-micro">شهادة إتقان</span>
                            <h3 class="tq-pastel__title" style="margin:var(--tq-space-xs) 0 0">
                                <?php echo html_escape($c['milestone_title'] ?: $c['path_title'] ?: 'محطة'); ?>
                            </h3>
                        </div>
                        <dl class="tq-s-list">
                            <div class="tq-s-row">
                                <dt class="tq-caption">نسبة الإتقان</dt>
                                <dd style="margin:0"><?php echo tq_num(((int) $c['score']) . '%'); ?></dd>
                            </div>
                            <div class="tq-s-row">
                                <dt class="tq-caption">تاريخ الإصدار</dt>
                                <dd style="margin:0"><?php echo tq_iso(html_escape((string) $c['submitted_at'])); ?></dd>
                            </div>
                            <div class="tq-s-row">
                                <dt class="tq-caption">رمز التحقق</dt>
                                <dd style="margin:0"><?php echo tq_num('TQ-' . str_pad((string) $c['id'], 6, '0', STR_PAD_LEFT), 'tq-num--sm'); ?></dd>
                            </div>
                        </dl>
                        <div class="tq-row" style="margin-block-start:var(--tq-space-l)">
                            <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo base_url('taqdar/certificate/' . (int) $c['id']); ?>">
                                <?php echo tq_icon('download'); ?> تنزيل
                            </a>
                            <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('taqdar/verify/' . (int) $c['id']); ?>">صفحة التحقق</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <aside class="tq-aside">
        <div class="tq-card">
            <h2 class="tq-card__title">كيف تصدر الشهادة</h2>
            <ol class="tq-s-list" style="counter-reset:s">
                <?php foreach ([
                    'تنهي دروس المحطة وتتقنها واحدا واحدا',
                    'تجتاز اختبار المحطة',
                    'تصلك شهادة بأهدافك المتقنة ورمز تحقق',
                ] as $n => $step): ?>
                    <div class="tq-s-row">
                        <span class="tq-icon-box tq-pastel--sky" aria-hidden="true"><?php echo tq_num($n + 1, 'tq-num--sm'); ?></span>
                        <span class="tq-caption"><?php echo html_escape($step); ?></span>
                    </div>
                <?php endforeach; ?>
            </ol>
            <p class="tq-micro" style="margin-block-start:var(--tq-space-l)">
                رمز التحقق يفتح صفحة عامة تؤكد صحة الشهادة لأي جهة.
            </p>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
