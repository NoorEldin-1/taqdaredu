<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * لوحة القيادة.
 *
 * ترتيبها مقصود: **ما ينتظرك** أولا، ثم **ما يجري**، ثم **ما ينقص**.
 * المسؤول يفتح اللوحة ليعرف ما يفعل اليوم لا ليقرأ رصيدا، فالرقم الذي
 * لا يتبعه فعل يوضع بعد الرقم الذي يتبعه.
 */

$q     = $readiness['_questions'];
$bound = (int) $q['bound'];
$total = (int) $q['total'];
$pct   = $total > 0 ? (int) floor(($bound * 100) / $total) : 0;

/* السلسلة التي يجب أن تكتمل ليعمل الإتقان — بترتيب التبعية لا الأهمية. */
$chain = array(
    array('k' => 'subjects',   'label' => 'المواد',    'need' => 'لا مسار بلا مادة'),
    array('k' => 'grades',     'label' => 'الصفوف',    'need' => 'لا مسار بلا صف'),
    array('k' => 'paths',      'label' => 'المسارات',  'need' => 'لا اشتراك بلا مسار'),
    array('k' => 'objectives', 'label' => 'الأهداف',   'need' => 'لا مراجعة بلا أهداف'),
);
$blocked = null;
foreach ($chain as $c) {
    if ((int) $readiness[$c['k']]['count'] === 0) { $blocked = $c; break; }
}

/** ريال من هللة، معزول اتجاهيا. */
$sar = function ($halalas) {
    return '<span class="tqa-num">' . number_format(((int) $halalas) / 100, 0) . '</span> ر.س';
};

/* ما ينتظر إجراء. الصفر لا يعرض: صف من الأصفار يعلم القارئ أن يتجاوز
   الصف كله، فيتجاوزه يوم يمتلئ. */
$waiting = array(
    array('n' => (int) ($queue['payouts'] ?? 0),      'label' => 'طلب سحب بلا رد',
          'href' => 'taqdar_admin/payouts?status=pending', 'icon' => 'send',  'tone' => 'rose'),
    array('n' => (int) ($queue['teacher_apps'] ?? 0), 'label' => 'طلب معلم بلا اعتماد',
          'href' => 'taqdar_admin/teachers',          'icon' => 'file',  'tone' => 'peach'),
    array('n' => (int) ($queue['sessions'] ?? 0),     'label' => 'حصة بلا رد',
          'href' => 'taqdar_admin/sessions?status=requested', 'icon' => 'video', 'tone' => 'lilac'),
    array('n' => (int) ($queue['subs_pending'] ?? 0), 'label' => 'اشتراك بلا تفعيل',
          'href' => 'taqdar_admin/subscriptions',     'icon' => 'refresh', 'tone' => 'sky'),
    array('n' => (int) ($queue['contact'] ?? 0),      'label' => 'رسالة تواصل لم تقرأ',
          'href' => 'admin/contact',                  'icon' => 'mail',  'tone' => 'sand'),
    array('n' => (int) ($queue['pending_courses'] ?? 0), 'label' => 'كورس بانتظار المراجعة',
          'href' => 'admin/courses',                  'icon' => 'book',  'tone' => 'mint'),
);
$waiting = array_values(array_filter($waiting, function ($w) { return $w['n'] > 0; }));

/* فرق الإيراد عن الشهر الماضي: رقم بلا مرجع لا يقال عنه جيد ولا سيئ. */
$rev_now  = (int) $pulse['revenue_month'];
$rev_prev = (int) $pulse['revenue_prev'];
$rev_diff = $rev_prev > 0 ? (int) round((($rev_now - $rev_prev) * 100) / $rev_prev) : null;
?>

<?php tqa_head('لوحة القيادة', 'ما ينتظرك اليوم، وما يجري في المنصة، وما ينقص لتكتمل.', 'meter'); ?>

<?php /* ══════════ ما ينتظرك ══════════ */ ?>
<?php if ($waiting): ?>
    <section class="tqa-card" style="margin-block-end:var(--tq-space-xl)">
        <h2 style="margin-block-end:var(--tq-space-l)">ينتظر إجراء منك</h2>
        <div class="tqa-grid tqa-grid--3">
            <?php foreach ($waiting as $w): ?>
                <a class="tqa-stat" href="<?php echo site_url($w['href']); ?>">
                    <div class="tqa-stat__top">
                        <span class="tqa-stat__label"><?php echo html_escape($w['label']); ?></span>
                        <span class="tqa-stat__icon tqa-<?php echo $w['tone']; ?>" aria-hidden="true">
                            <?php echo tq_icon($w['icon'], 18); ?>
                        </span>
                    </div>
                    <span class="tqa-stat__value"><?php echo $w['n']; ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php else: ?>
    <div class="tqa-note" style="margin-block-end:var(--tq-space-xl)">
        <span aria-hidden="true"><?php echo tq_icon('check', 18); ?></span>
        <span><strong>لا شيء ينتظر.</strong>
        لا طلب سحب معلق، ولا طلب معلم بلا اعتماد، ولا حصة بلا رد.</span>
    </div>
<?php endif; ?>

<?php /* ══════════ ما يجري ══════════ */ ?>
<section style="margin-block-end:var(--tq-space-xl)">
    <h2 style="margin-block-end:var(--tq-space-l)">المنصة الآن</h2>
    <div class="tqa-grid tqa-grid--4">

        <div class="tqa-stat">
            <div class="tqa-stat__top">
                <span class="tqa-stat__label">إيراد هذا الشهر</span>
                <span class="tqa-stat__icon tqa-mint" aria-hidden="true"><?php echo tq_icon('wallet', 18); ?></span>
            </div>
            <span class="tqa-stat__value"><?php echo $sar($rev_now); ?></span>
            <span class="tqa-stat__hint">
                <?php if ($rev_diff === null): ?>
                    لا إيراد في الشهر الماضي فلا مقارنة
                <?php elseif ($rev_diff >= 0): ?>
                    أعلى بـ <span class="tqa-num"><?php echo $rev_diff; ?>%</span> عن الشهر الماضي
                <?php else: ?>
                    أقل بـ <span class="tqa-num"><?php echo abs($rev_diff); ?>%</span> عن الشهر الماضي
                <?php endif; ?>
            </span>
        </div>

        <a class="tqa-stat" href="<?php echo site_url('taqdar_admin/subscriptions'); ?>">
            <div class="tqa-stat__top">
                <span class="tqa-stat__label">اشتراكات نشطة</span>
                <span class="tqa-stat__icon tqa-sky" aria-hidden="true"><?php echo tq_icon('refresh', 18); ?></span>
            </div>
            <span class="tqa-stat__value"><?php echo (int) $pulse['subs_active']; ?></span>
            <span class="tqa-stat__hint">تدفع وتتجدد</span>
        </a>

        <a class="tqa-stat" href="<?php echo site_url('taqdar_admin/people?role=student'); ?>">
            <div class="tqa-stat__top">
                <span class="tqa-stat__label">الطلاب</span>
                <span class="tqa-stat__icon tqa-lilac" aria-hidden="true"><?php echo tq_icon('users', 18); ?></span>
            </div>
            <span class="tqa-stat__value"><?php echo (int) $pulse['students']; ?></span>
            <span class="tqa-stat__hint">
                و<span class="tqa-num"><?php echo (int) $pulse['parents']; ?></span> ولي أمر
            </span>
        </a>

        <a class="tqa-stat" href="<?php echo site_url('taqdar_admin/people?role=teacher'); ?>">
            <div class="tqa-stat__top">
                <span class="tqa-stat__label">المعلمون</span>
                <span class="tqa-stat__icon tqa-peach" aria-hidden="true"><?php echo tq_icon('graduation', 18); ?></span>
            </div>
            <span class="tqa-stat__value"><?php echo (int) $pulse['teachers']; ?></span>
            <span class="tqa-stat__hint">معتمدون ويدرسون</span>
        </a>

        <a class="tqa-stat" href="<?php echo site_url('taqdar_admin/module/paths'); ?>">
            <div class="tqa-stat__top">
                <span class="tqa-stat__label">مسارات منشورة</span>
                <span class="tqa-stat__icon tqa-mint" aria-hidden="true"><?php echo tq_icon('target', 18); ?></span>
            </div>
            <span class="tqa-stat__value"><?php echo (int) $pulse['paths_live']; ?></span>
            <span class="tqa-stat__hint">
                <?php if ((int) $pulse['paths_draft'] > 0): ?>
                    و<span class="tqa-num"><?php echo (int) $pulse['paths_draft']; ?></span> مسودة لم تنشر بعد
                <?php else: ?>
                    ولا مسودة معلقة
                <?php endif; ?>
            </span>
        </a>

        <a class="tqa-stat" href="<?php echo site_url('admin/courses'); ?>">
            <div class="tqa-stat__top">
                <span class="tqa-stat__label">الدروس</span>
                <span class="tqa-stat__icon tqa-sky" aria-hidden="true"><?php echo tq_icon('play', 18); ?></span>
            </div>
            <span class="tqa-stat__value"><?php echo (int) $pulse['lessons']; ?></span>
            <span class="tqa-stat__hint">
                و<span class="tqa-num"><?php echo (int) $pulse['objectives']; ?></span> هدفا تعليميا
            </span>
        </a>

        <a class="tqa-stat" href="<?php echo site_url('taqdar_admin/mastery'); ?>">
            <div class="tqa-stat__top">
                <span class="tqa-stat__label">تقييمات هذا الأسبوع</span>
                <span class="tqa-stat__icon tqa-sand" aria-hidden="true"><?php echo tq_icon('check-badge', 18); ?></span>
            </div>
            <span class="tqa-stat__value"><?php echo (int) $pulse['attempts_week']; ?></span>
            <span class="tqa-stat__hint">محاولة سلمت في آخر سبعة أيام</span>
        </a>

        <a class="tqa-stat" href="<?php echo site_url('taqdar_admin/payouts'); ?>">
            <div class="tqa-stat__top">
                <span class="tqa-stat__label">إيراد الشهر الماضي</span>
                <span class="tqa-stat__icon tqa-rose" aria-hidden="true"><?php echo tq_icon('receipt', 18); ?></span>
            </div>
            <span class="tqa-stat__value"><?php echo $sar($rev_prev); ?></span>
            <span class="tqa-stat__hint">للمقارنة</span>
        </a>
    </div>
</section>

<?php /* ══════════ ما ينقص ══════════ */ ?>
<div class="tqa-grid tqa-grid--2">

    <section class="tqa-card">
        <h2>جاهزية المنهج</h2>
        <?php if ($blocked): ?>
            <p class="tqa-pagehead__sub" style="margin-block:var(--tq-space-s) var(--tq-space-l)">
                دورة التعلم متوقفة عند «<?php echo html_escape($blocked['label']); ?>».
                <?php echo html_escape($blocked['need']); ?>. وما دامت هذه الخطوة فارغة
                فبوابة الإتقان لا تجد ما تحكم به، ويبقى كل درس بعد الأول مقفلا أمام الطالب.
            </p>
            <a class="tqa-btn tqa-btn--primary" href="<?php echo site_url('taqdar_admin/module/' . $blocked['k']); ?>">
                ابدأ من <?php echo html_escape($readiness[$blocked['k']]['title']); ?>
            </a>
        <?php else: ?>
            <p class="tqa-pagehead__sub" style="margin-block:var(--tq-space-s) var(--tq-space-l)">
                المواد والصفوف والمسارات والأهداف كلها ممتلئة، فبوابة الإتقان قادرة على الحكم.
            </p>
            <div class="tqa-grid tqa-grid--2">
                <?php foreach ($chain as $c): ?>
                    <a class="tqa-stat" href="<?php echo site_url('taqdar_admin/module/' . $c['k']); ?>">
                        <span class="tqa-stat__label"><?php echo html_escape($c['label']); ?></span>
                        <span class="tqa-stat__value" style="font:var(--tq-type-numeralMd)">
                            <?php echo (int) $readiness[$c['k']]['count']; ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="tqa-card">
        <h2>ربط الأسئلة بالأهداف</h2>
        <p class="tqa-pagehead__sub" style="margin-block:var(--tq-space-s) var(--tq-space-l)">
            السؤال غير المربوط بهدف يصحح ولا يوجه: حين يخطئ فيه الطالب لا يعرف
            النظام إلى أي لحظة من الشرح يعيده. وهذا هو الفرق بين اختبار وبين تعلم.
        </p>

        <div class="tqa-bar" role="img"
             aria-label="مربوط <?php echo $pct; ?> بالمئة من الأسئلة">
            <div class="tqa-bar__fill" style="inline-size:<?php echo $pct; ?>%"></div>
        </div>
        <p style="margin-block:var(--tq-space-s) var(--tq-space-l);font:var(--tq-type-caption);color:var(--tq-text2)">
            مربوط <span class="tqa-num"><?php echo $bound; ?></span>
            من <span class="tqa-num"><?php echo $total; ?></span> سؤالا
            (<span class="tqa-num"><?php echo $pct; ?>%</span>)
        </p>

        <a class="tqa-btn tqa-btn--ghost" href="<?php echo site_url('taqdar_admin/bindings'); ?>">افتح شاشة الربط</a>
    </section>
</div>
