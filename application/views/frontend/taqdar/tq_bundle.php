<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * محتوى باقتي — ما دفع الطالب ثمنه، مرتَّبًا كما يُدرَس.
 *
 * صفحة الاشتراك تقول «نشط حتى كذا» وتعرض فاتورة، ولا تقول **ماذا فُتح**.
 * فمن اشترك يرى حالةً وتاريخًا ثمّ يبحث عن دروسه في قائمةٍ جانبية.
 *
 * وهذه تقولها: الموادّ بوحداتها ودروسها، وتقدّمُه في كلٍّ منها، ورابطٌ
 * لكلّ درس. والمصدر `bundle_by_code()` نفسه الذي تقرأ منه صفحة الباقة
 * العامّة — فما وُعِد به قبل الدفع هو ما يُعرَض بعده، حرفًا بحرف.
 */
$b    = isset($tq_bundle) ? $tq_bundle : null;
$sub  = isset($tq_sub) ? $tq_sub : null;
$prog = isset($tq_progress) ? $tq_progress : array();

/* الحال الفعلية لا المخزَّنة — كما في صفحة الاشتراك تمامًا. */
$eff = $sub ? $sub['status'] : null;
if ($sub && in_array($eff, array('active', 'cancelled'), true)
    && !empty($sub['ends_at']) && strtotime($sub['ends_at']) < time()) {
    $eff = 'expired';
}
$live = in_array($eff, array('active', 'cancelled'), true);
?>

<div class="tq-page">
    <header class="tq-page__head">
        <h1 class="tq-h1">محتوى باقتي</h1>
        <p class="tq-caption">كلّ ما فتحته باقتك — بموادّه ووحداته ودروسه.</p>
    </header>

    <?php if (!$sub): ?>

        <div class="tq-card tq-card--panel">
            <h2 class="tq-card__title">لا اشتراك بعد</h2>
            <p class="tq-caption">اختر باقةً تفتح منهج صفّك كاملًا — لا مادّةً مادّة.</p>
            <a class="tq-btn tq-btn--primary" href="<?php echo base_url('plans'); ?>">اطّلع على الباقات</a>
        </div>

    <?php elseif (!$live): ?>

        <?php /* اشتراكٌ معلَّق أو منتهٍ: المحتوى لم يُفتح بعد، وعرضُ منهجٍ
                 كامل هنا يوهم بأنّه متاح. يُقال سبب الإغلاق ويُدَلّ على بابه. */ ?>
        <div class="tq-card tq-card--panel">
            <h2 class="tq-card__title">
                <?php echo $eff === 'pending' ? 'اشتراكك بانتظار السداد' : 'انتهت مدّة اشتراكك'; ?>
            </h2>
            <p class="tq-caption">
                <?php if ($eff === 'pending'): ?>
                    صدرت فاتورتك، ويُفتح المحتوى فور التحقّق من حوالتك.
                <?php else: ?>
                    يمكنك الاشتراك من جديد ويعود ما كنت تدرسه كما تركته.
                <?php endif; ?>
            </p>
            <a class="tq-btn tq-btn--primary" href="<?php echo base_url('student/subscription'); ?>">تفاصيل اشتراكي</a>
        </div>

    <?php elseif (!$b || empty($b['subjects'])): ?>

        <div class="tq-card tq-card--panel">
            <h2 class="tq-card__title">باقتك نشطة</h2>
            <p class="tq-caption">
                برامج هذه الباقة قيد التجهيز، وتظهر لك هنا تلقائيًّا فور نشرها.
            </p>
        </div>

    <?php else: ?>

        <?php $t = $b['totals']; ?>

        <div class="tq-card tq-card--panel tqb-head">
            <div class="tqb-head__b">
                <h2 class="tq-card__title"><?php echo html_escape($b['name']); ?></h2>
                <?php if (!empty($sub['ends_at'])): ?>
                    <p class="tq-caption">
                        <?php echo $eff === 'cancelled' ? 'صالح حتى' : 'ينتهي في'; ?>
                        <span class="tq-ltr" dir="ltr"><?php echo date('Y-m-d', strtotime($sub['ends_at'])); ?></span>
                    </p>
                <?php endif; ?>
            </div>
            <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo base_url('student/subscription'); ?>">
                تفاصيل الاشتراك
            </a>
        </div>

        <?php
        /* الإجماليّات — وما كان صفرًا لا يُعرَض بندًا فارغًا. */
        echo tqs_stat_strip(array(
            array($t['subjects'], 'مادّة',    'i-book'),
            array($t['units'],    'وحدة',     'i-grid'),
            array($t['lessons'],  'درسًا',    'i-play'),
            array($t['quizzes'],  'اختبارًا', 'i-clipboard'),
        ), 'tqb-stats');
        ?>

        <?php if ($t['lessons'] > 0): ?>
            <?php
            /* تقدّمٌ إجماليّ: متوسّطُ ما أُنجز في المقرّرات التي لها محتوى.
               والمقرّر الفارغ لا يُحسب — وإلّا هبطت النسبة بما لم يُنشَر بعد. */
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
                    <span>تقدّمك في الباقة</span>
                    <b class="tq-ltr"><?php echo $pct; ?>%</b>
                </div>
                <div class="tqb-prog__bar"><i style="inline-size:<?php echo $pct; ?>%"></i></div>
            </div>
        <?php endif; ?>

        <section class="tq-section">
            <h2 class="tq-h2">المنهج</h2>
            <p class="tq-caption">
                يُفتح الدرس التالي بعد إتقان الذي قبله — فلا يُبنى على أساسٍ لم يُتقَن.
            </p>
            <?php echo tqs_curriculum($b, array(
                'mode' => 'student', 'open' => 1, 'progress' => $prog,
            )); ?>
        </section>

    <?php endif; ?>
</div>
