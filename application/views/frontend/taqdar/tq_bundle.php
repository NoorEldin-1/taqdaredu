<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * محتوى باقتي — ما دفع الطالب ثمنه، مرتبا كما يدرس.
 *
 * صفحة الاشتراك تقول «نشط حتى كذا» وتعرض فاتورة، ولا تقول **ماذا فتح**.
 * فمن اشترك يرى حالة وتاريخا ثم يبحث عن دروسه في قائمة جانبية.
 *
 * وهذه تقولها: المواد بوحداتها ودروسها، وتقدمه في كل منها، ورابط
 * لكل درس. والمصدر `bundle_by_code()` نفسه الذي تقرأ منه صفحة الباقة
 * العامة — فما وعد به قبل الدفع هو ما يعرض بعده، حرفا بحرف.
 *
 * وكانت تعرض **خارج البوابة كلها**: اسمها غائب عن `$tq_portal_pages` في
 * الغلاف، فتحمل ترويسة Academy القديمة وتذييلها حول محتوى مكتوب بمفردات
 * تقدر. وغلافها الآن غلاف أخواتها.
 */
/* `tq_subscription` لا `tq_sub`: الأخير اسم محجوز في غلاف البوابة لسطر
   تحت العنوان. وتسمية واحدة لمعنيين تجعل ضبط العنوان يمحو الاشتراك. */
$b    = isset($tq_bundle) ? $tq_bundle : null;
$sub  = isset($tq_subscription) ? $tq_subscription : null;
$prog = isset($tq_progress) ? $tq_progress : array();
/* TQ-BUNDLE-SINGLE — مشتريات مفردة سارية لمن لا باقة له (كورس · كتاب · مسار). */
$singles = isset($tq_singles) && is_array($tq_singles) ? $tq_singles : array();

/* الحال الفعلية لا المخزنة — كما في صفحة الاشتراك تماما. */
$eff = $sub ? $sub['status'] : null;
if ($sub && in_array($eff, array('active', 'cancelled'), true)
    && !empty($sub['ends_at']) && strtotime($sub['ends_at']) < time()) {
    $eff = 'expired';
}
$live = in_array($eff, array('active', 'cancelled'), true);

$tq_nav   = 'bundle';
$tq_role  = 'student';
$tq_title = t('محتوى باقتي');
$tq_sub   = t('كل ما فتحته باقتك — بمواده ووحداته ودروسه.');
$tq_icon  = 'grid';

include 'portal_open.php';
?>

<div class="tq-stack">

    <?php if (!$sub && $singles): ?>

        <div class="tq-card tq-card--panel">
            <h2 class="tq-card__title"><?php echo t('ليست لك باقة — ولك مشتريات مفردة'); ?></h2>
            <p class="tq-caption">
                <?php echo t('هذه الصفحة تعرض محتوى الباقة. وما اشتريته مفردا مفتوح لك في مكانه:'); ?>
            </p>
            <ul class="tq-stack" style="margin-block:var(--tq-space-m)">
                <?php foreach ($singles as $tq_si): ?>
                    <li>
                        <span class="tq-badge"><?php echo html_escape($tq_si['label']); ?></span>
                        <strong><?php echo html_escape($tq_si['title']); ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="tq-row" style="gap:var(--tq-space-s);flex-wrap:wrap">
                <a class="tq-btn tq-btn--primary" href="<?php echo base_url('student/courses'); ?>"><?php echo t('كورساتي'); ?></a>
                <a class="tq-btn tq-btn--secondary" href="<?php echo base_url('student/library'); ?>"><?php echo t('كتبي وملخصاتي'); ?></a>
                <a class="tq-btn tq-btn--ghost" href="<?php echo base_url('plans'); ?>"><?php echo t('اطلع على الباقات'); ?></a>
            </div>
        </div>

    <?php elseif (!$sub): ?>

        <div class="tq-card tq-card--panel">
            <h2 class="tq-card__title"><?php echo t('لا اشتراك بعد'); ?></h2>
            <p class="tq-caption"><?php echo t('اختر باقة تفتح منهج صفك كاملا — لا مادة مادة.'); ?></p>
            <a class="tq-btn tq-btn--primary" href="<?php echo base_url('plans'); ?>"><?php echo t('اطلع على الباقات'); ?></a>
        </div>

    <?php elseif (!$live): ?>

        <?php /* اشتراك معلق أو منته: المحتوى لم يفتح بعد، وعرض منهج
                 كامل هنا يوهم بأنه متاح. يقال سبب الإغلاق ويدل على بابه. */ ?>
        <div class="tq-card tq-card--panel">
            <h2 class="tq-card__title">
                <?php echo $eff === 'pending' ? t('اشتراكك بانتظار السداد') : t('انتهت مدة اشتراكك'); ?>
            </h2>
            <p class="tq-caption">
                <?php if ($eff === 'pending'): ?>
                    <?php echo t('صدرت فاتورتك، ويفتح المحتوى فور التحقق من حوالتك.'); ?>
                <?php else: ?>
                    <?php echo t('يمكنك الاشتراك من جديد ويعود ما كنت تدرسه كما تركته.'); ?>
                <?php endif; ?>
            </p>
            <a class="tq-btn tq-btn--primary" href="<?php echo base_url('student/subscription'); ?>"><?php echo t('تفاصيل اشتراكي'); ?></a>
        </div>

    <?php elseif (!$b || empty($b['subjects'])): ?>

        <div class="tq-card tq-card--panel">
            <h2 class="tq-card__title"><?php echo t('باقتك نشطة'); ?></h2>
            <p class="tq-caption">
                <?php echo t('برامج هذه الباقة قيد التجهيز، وتظهر لك هنا تلقائيا فور نشرها.'); ?>
            </p>
        </div>

    <?php else: ?>

        <?php $t = $b['totals']; ?>

        <div class="tq-card tq-card--panel tqb-head">
            <div class="tqb-head__b">
                <h2 class="tq-card__title"><?php echo html_escape($b['name']); ?></h2>
                <?php if (!empty($sub['ends_at'])): ?>
                    <p class="tq-caption">
                        <?php echo $eff === 'cancelled' ? t('صالح حتى') : t('ينتهي في'); ?>
                        <span class="tq-ltr" dir="ltr"><?php echo date('Y-m-d', strtotime($sub['ends_at'])); ?></span>
                    </p>
                <?php endif; ?>
            </div>
            <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo base_url('student/subscription'); ?>">
                <?php echo t('تفاصيل الاشتراك'); ?>
            </a>
        </div>

        <?php
        /* TQ-BUNDLE-COUNT — عدد المواد هو عدد ما تعرضه الشجرة تحته: كان الملخص
           يعد المادة الواحدة مرة (١١) والشجرة تعرضها في كل صف (١٥)، فيعد الطالب
           ما تحت ولا يتفق الرقمان. */
        $tq_rows = 0;
        foreach ((isset($b['grades']) && is_array($b['grades'])) ? $b['grades'] : array() as $tq_g) {
            $tq_rows += isset($tq_g['subjects']) && is_array($tq_g['subjects']) ? count($tq_g['subjects']) : 0;
        }
        if ($tq_rows < 1) $tq_rows = (int) $t['subjects'];

        /* الإجماليات — وما كان صفرا لا يعرض بندا فارغا. */
        echo tqs_stat_strip(array(
            array($tq_rows,       t('مادة'),    'i-book'),
            array($t['units'],    t('وحدة'),     'i-grid'),
            array($t['lessons'],  t('درسا'),    'i-play'),
            array($t['quizzes'],  t('اختبارا'), 'i-clipboard'),
        ), 'tqb-stats');
        ?>

        <?php if ($t['lessons'] > 0): ?>
            <?php
            /* تقدم إجمالي: متوسط ما أنجز في المقررات التي لها محتوى.
               والمقرر الفارغ لا يحسب — وإلا هبطت النسبة بما لم ينشر بعد. */
            $done = 0; $n = 0;
            foreach ($b['subjects'] as $s) {
                if (!$s['ready']) continue;
                $n++;
                $done += isset($prog[$s['course_id']]) ? (int) $prog[$s['course_id']] : 0;
            }
            $pct = $n > 0 ? (int) round($done / $n) : 0;
            ?>
            <div class="tq-card tqb-prog">
                <div class="tqb-prog__t">
                    <span><?php echo t('تقدمك في الباقة'); ?></span>
                    <b class="tq-ltr"><?php echo $pct; ?>%</b>
                </div>
                <div class="tqb-prog__bar"><i style="inline-size:<?php echo $pct; ?>%"></i></div>
            </div>
        <?php endif; ?>

        <div class="tq-card tq-card--panel">
            <div class="tq-card__head">
                <h2 class="tq-card__title"><?php echo t('المنهج'); ?></h2>
            </div>
            <p class="tq-caption">
                <?php echo t('يفتح الدرس التالي بعد إتقان الذي قبله — فلا يبنى على أساس لم يتقن.'); ?>
            </p>
            <?php /* TQ-CURRIC-TREE — نفس شجرة صفحة البيع: من دفع يرى
                     الترتيب الذي اشترى على أساسه، لا ترتيبا آخر. */ ?>
            <?php echo tqs_curriculum_tree($b, array(
                'mode' => 'student', 'progress' => $prog,
            )); ?>
        </div>

    <?php endif; ?>
</div>

<?php include 'portal_close.php'; ?>
