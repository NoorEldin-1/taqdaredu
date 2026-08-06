<?php
defined('BASEPATH') or exit('No direct script access allowed');

$q       = $readiness['_questions'];
$bound   = $q['bound'];
$total   = $q['total'];
$pct     = $total > 0 ? (int) floor(($bound * 100) / $total) : 0;

/* السلسلة التي يجب أن تكتمل ليعمل الإتقان — بترتيب التبعية لا بترتيب الأهمية. */
$chain = array(
    array('k' => 'subjects',   'label' => 'مواد',      'need' => 'لا مسار بلا مادة'),
    array('k' => 'grades',     'label' => 'صفوف',      'need' => 'لا مسار بلا صف'),
    array('k' => 'paths',      'label' => 'مسارات',    'need' => 'لا اشتراك بلا مسار'),
    array('k' => 'objectives', 'label' => 'أهداف',     'need' => 'لا مراجعة بلا أهداف'),
);
$blocked = null;
foreach ($chain as $c) {
    if ($readiness[$c['k']]['count'] === 0) { $blocked = $c; break; }
}
?>

<div class="tqa-head">
    <div>
        <h1>منصة تقدر</h1>
        <p>حالة المنصة الآن، وما يلزم لتشتغل دورة التعلم كاملة.</p>
    </div>
</div>

<?php tqa_flash(); ?>

<?php if ($blocked): ?>
    <div class="tqa-block">
        <h3>دورة التعلم متوقفة عند: <?php echo html_escape($blocked['label']); ?></h3>
        <p>
            <?php echo html_escape($blocked['need']); ?>.
            وما دامت هذه الخطوة فارغة، فبوابة الإتقان لا تجد ما تحكم به،
            ويبقى كل درس بعد الأول مقفلا أمام الطالب.
        </p>
        <a class="btn btn-primary" href="<?php echo site_url('taqdar_admin/module/' . $blocked['k']); ?>">
            ابدأ من هنا — <?php echo html_escape($readiness[$blocked['k']]['title']); ?>
        </a>
    </div>
<?php else: ?>
    <div class="tqa-note">
        <strong>السلسلة مكتملة.</strong>
        المواد والصفوف والمسارات والأهداف كلها ممتلئة، فبوابة الإتقان قادرة على الحكم.
    </div>
<?php endif; ?>

<div class="row">
<?php foreach ($readiness as $key => $r): if ($key === '_questions') continue; ?>
    <div class="col-md-4 col-sm-6">
        <a class="tqa-stat" href="<?php echo site_url('taqdar_admin/module/' . $key); ?>">
            <span class="tqa-stat-label"><?php echo html_escape($r['title']); ?></span>
            <span class="tqa-stat-num tq-ltr" dir="ltr"><?php echo (int) $r['count']; ?></span>
        </a>
    </div>
<?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header"><h4 class="header-title">ربط الأسئلة بالأهداف</h4></div>
    <div class="card-body">
        <p>
            السؤال غير المربوط بهدف يستطيع أن يصحح، لكنه لا يستطيع أن يوجه:
            حين يخطئ فيه الطالب لا يعرف النظام إلى أي لحظة من الشرح يعيده.
            وهذا هو الفرق بين اختبار وبين تعلم.
        </p>

        <div class="tqa-bar">
            <div class="tqa-bar-fill" style="inline-size: <?php echo (int) $pct; ?>%"></div>
        </div>
        <p class="tqa-bar-cap">
            مربوط <span class="tq-ltr" dir="ltr"><?php echo $bound; ?></span>
            من <span class="tq-ltr" dir="ltr"><?php echo $total; ?></span> سؤالا
            (<span class="tq-ltr" dir="ltr"><?php echo $pct; ?>%</span>)
        </p>

        <a class="btn btn-primary" href="<?php echo site_url('taqdar_admin/bindings'); ?>">افتح شاشة الربط</a>
    </div>
</div>
