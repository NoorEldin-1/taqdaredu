<?php
/**
 * غلاف اللوحة.
 *
 * بني على هيكل `tq-shell` نفسه الذي تبنى عليه البوابات الثلاث: شريط
 * جانبي لاصق بارتفاع النافذة، ترويسة لاصقة، ومنطقة صفحة تمرر وحدها.
 * وكان الغلاف السابق `container-fluid > wrapper > content-page` من قالب
 * Hyper، وهو سبب الفارق البصري الأول بين اللوحة والبوابات: خلفية أخرى،
 * وحواف أحد، وشريط يطول بطول قائمته فتسقط بنوده الأخيرة تحت حافة النافذة.
 *
 * الرسائل الطائرة تعرض **هنا** لا في كل شاشة: `Taqdar_admin.php` وحده
 * يودع ثلاثين رسالة بعد كل حفظ وتفعيل واعتماد، وشاشات Academy تودع
 * أضعافها — وكانت كل شاشة تعرضها بيدها أو لا تعرضها. فمن يحفظ في شاشة
 * لم تكتب سطر العرض لا يقال له «حفظ» ولا «لم يحفظ».
 */
$system_title        = get_settings('system_title') ?: 'منصة تقدر التعليمية';
$logged_in_user_role = strtolower($this->session->userdata('role'));
$page_name           = isset($page_name) ? $page_name : 'dashboard';
$page_title          = isset($page_title) ? $page_title : 'لوحة الإدارة';

/* TQ-I18N — اللغة والاتجاه من `tq_lang()` وحدها، كالواجهة الأمامية. */
$tqa_active = tq_lang();
$tqa_dir    = tq_dir();
$tqa_iso    = tq_iso();
$tqa_lang   = $tqa_active;
?>
<!DOCTYPE html>
<html lang="<?php echo html_escape($tqa_iso); ?>" dir="<?php echo html_escape($tqa_dir); ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?php echo html_escape($page_title); ?> | <?php echo html_escape($system_title); ?></title>

    <?php /* اللوحة لا تفهرس: صفحاتها خلف تسجيل دخول، وفهرستها تسرب
             أسماء المسارات لمن يقرأ نتائج البحث. */ ?>
    <meta name="robots" content="noindex, nofollow">

    <?php include 'metas.php'; ?>
    <?php include 'includes_top.php'; ?>
</head>

<body class="tqa" data-layout="detached">

<div class="tqa-shell" id="tqa-shell">

<?php /**
 * الطي يقرأ **هنا** لا في الذيل.
 *
 * TQ-RAIL-FLASH — كانت القراءة في سكربت آخر الجسم، أي بعد رسم الشريط
 * كاملا: فمن طوى الشريط يرى في كل صفحة شريطا بعرض ٢٦٤ بكسل بأسمائه
 * كلها ثم ينكمش أمامه إلى ٧٦ — ومعه ينزاح عرض الصفحة كلها. هذا السطر
 * ينفذ قبل أن يوجد الشريط في الشجرة، فيولد بحالته الصحيحة ولا ينكمش.
 */ ?>
<script>
(function () {
    try {
        if (localStorage.getItem('tqa-rail-collapsed') === '1') {
            document.getElementById('tqa-shell').setAttribute('data-collapsed', 'true');
        }
    } catch (e) { /* التخزين محجوب في وضع التصفح الخاص — الطي يعمل بلا حفظ */ }
})();
</script>

    <?php include $logged_in_user_role . '/navigation.php'; ?>

    <div class="tqa-main">

        <?php include 'header.php'; ?>

        <main class="tqa-page" id="tqa-content">
            <?php
            /* رسالة واحدة من كل نوع، وبأدوار وصول صحيحة: الخطأ `alert`
               يقاطع قارئ الشاشة، والنجاح `status` ينتظر دوره. */
            foreach (array(
                'error_message' => 'err',
                'flash_message' => 'ok',
                'info_message'  => 'ok',
            ) as $tqa_fk => $tqa_fc):
                $tqa_fm = $this->session->flashdata($tqa_fk);
                if (empty($tqa_fm)) continue; ?>
                <p class="tqa-flash tqa-flash--<?php echo $tqa_fc; ?>"
                   role="<?php echo $tqa_fc === 'err' ? 'alert' : 'status'; ?>">
                    <?php echo html_escape($tqa_fm); ?>
                </p>
            <?php endforeach; ?>

            <?php include $logged_in_user_role . '/' . $page_name . '.php'; ?>
        </main>
    </div>
</div>

<?php include 'includes_bottom.php'; ?>
<?php include 'modal.php'; ?>
<?php include 'common_scripts.php'; ?>

<script>
/**
 * سلوك الشريط الجانبي.
 *
 * على المكتب: طي يحفظ على الجهاز. وعلى الجوال: درج يفتح فوق المحتوى
 * ويغلق بالطبقة أو بمفتاح Escape أو بزره — ثلاثة أبواب لأن من فتح درجا
 * على جوال لا يجد الزر الذي فتحه إذ صار تحت الطبقة.
 */
(function () {
    var shell = document.getElementById('tqa-shell');
    if (!shell) return;

    /* الحالة الأولى قرئت في رأس `tqa-shell` قبل رسم الشريط — انظر هناك. */
    var KEY = 'tqa-rail-collapsed';

    var collapse = shell.querySelector('[data-tqa-collapse]');
    if (collapse) {
        /* الزر يعلن ما سيفعله لا ما فعل: مطويا هو «إظهار». وبلا هذا يبقى
           اسمه «طي القائمة» على شريط مطوي أصلا — وصف يناقض ما يفعله. */
        var label = function (collapsed) {
            var t = collapsed ? 'إظهار القائمة الجانبية' : 'طي القائمة الجانبية';
            collapse.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            collapse.setAttribute('aria-label', t);
            collapse.setAttribute('title', t);
        };

        label(shell.getAttribute('data-collapsed') === 'true');

        collapse.addEventListener('click', function () {
            var on = shell.getAttribute('data-collapsed') === 'true';
            shell.setAttribute('data-collapsed', on ? 'false' : 'true');
            label(!on);
            try { localStorage.setItem(KEY, on ? '0' : '1'); } catch (e) {}
        });
    }

    function setOpen(open) {
        shell.setAttribute('data-open', open ? 'true' : 'false');
        Array.prototype.forEach.call(
            shell.querySelectorAll('[data-tqa-toggle][aria-expanded]'),
            function (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); }
        );
    }

    Array.prototype.forEach.call(shell.querySelectorAll('[data-tqa-toggle]'), function (btn) {
        btn.addEventListener('click', function () {
            setOpen(shell.getAttribute('data-open') !== 'true');
        });
    });

    var scrim = shell.querySelector('[data-tqa-scrim]');
    if (scrim) scrim.addEventListener('click', function () { setOpen(false); });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && shell.getAttribute('data-open') === 'true') setOpen(false);
    });

    /**
     * البند المظلل يجلب إلى الرؤية.
     *
     * TQ-RAIL-OFFSCREEN — قائمة اللوحة ثمانية وثلاثون بندا في ثماني
     * مجموعات، وارتفاعها نحو ألف وثلاثمئة بكسل بينما الشريط بارتفاع
     * النافذة. أي أن **أكثر من نصف القائمة تحت الطية دائما**، والبند
     * المظلل — وهو الصفحة التي تقرؤها الآن — خارج الرؤية في أغلب
     * الشاشات: المسؤولون، والبنك، وبوابات الدفع، والمدونة، والرسائل،
     * وكل مجموعة «النظام». فيفتح المستخدم الصفحة ولا يرى في الشريط ما
     * يدل على موضعه، ويظن أن التظليل لا يعمل.
     *
     * والجلب يجري بلا حركة (`instant`): التمرير المتحرك عند كل تحميل
     * صفحة يقرأ اضطرابا لا إرشادا. و`nearest` تعني: لا تحرك شيئا إن
     * كان البند ظاهرا أصلا.
     */
    var current = shell.querySelector('.tqa-rail__item[aria-current="page"]');
    if (current && current.scrollIntoView) {
        try {
            current.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'instant' });
        } catch (e) {
            /* المتصفحات التي لا تعرف `behavior: instant` ترمي — والقيمة
               الافتراضية عندها فورية أصلا. */
            current.scrollIntoView(false);
        }
    }
})();
</script>

</body>
</html>
