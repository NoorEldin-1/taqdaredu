<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * قسمة إيراد هذه الباقة — بالريال، قبل الحفظ.
 *
 * السؤال الذي تفتح هذه اللوحة من أجله: «وضعت نسبة ١٥٪، فكم يأخذ كل معلم
 * فعلا؟». وهو سؤال لا يجيبه حقل النسبة وحده: الجواب يعتمد على من في
 * نطاق الباقة وكم درسا لكل منهم — وهذان لا يعرفان إلا باستعلام.
 *
 * وبلا هذه اللوحة يحفظ المسؤول نسبة ثم ينتظر أول بيعة ليكتشف أن معلما
 * أخذ ثلاثة أرباع الوعاء لأن كورسه فيه مئتا درس، أو أن الوعاء لم يوزع
 * أصلا لأن مسارات الصفوف بلا معلم مسند. والاكتشاف بعد البيعة يعني قيدا
 * في دفتر معلم — والقيد لا يعدل، يقابل بعكسه.
 *
 * والحساب هنا من `Taqdar_revenue_model::split()` نفسها التي تنفذ القسمة
 * وقت التفعيل، لا من نسخة ثانية من قواعدها. فما يعرض هو ما سيقيد حرفا
 * بحرف — ونسختان من قسمة المال تفترقان عند أول تعديل.
 *
 * ولوحة الخادم هي المرجع: السكربت يحدث الأرقام بعد تغيير السعر أو
 * النسبة (`tqa_plan_js`)، ومن لم يصله الملف يرى قسمة صحيحة للقيمة
 * المحفوظة — لا لوحة فارغة.
 */
$M   = &get_instance()->taqdar_admin_model;
$CI  = &get_instance();
$CI->load->model('taqdar_revenue_model');
$REV = $CI->taqdar_revenue_model;
$REV->install_schema();

/* الصف المحفوظ إن كان، وإلا هيكل فارغ بالنطاق الافتراضي — فاللوحة تظهر
   في «إضافة» أيضا وتقول «اختر صفوفها» بدل أن تغيب فلا يعرف أحد أنها
   موجودة. */
$plan = $row ? $row : array(
    'id' => 0, 'code' => '', 'name_ar' => '', 'price' => 0,
    'scope' => 'grade', 'scope_ids' => '', 'scope_id' => 0,
    'teacher_pool_percent' => null,
);

$split   = $REV->split($plan);
$default = $REV->default_pool_percent();
$is_def  = !$row || $plan['teacher_pool_percent'] === null || $plan['teacher_pool_percent'] === '';

$names = $M->options('teachers');

/* الخريطة إلى المتصفح: قيود دورات لكل صف، يزيل تكرارها ويقسم بها وأنت
   تعلم الصفوف. والأسماء معها — لصف يظهر معلمه لأول مرة بعد اختياره. */
$wmap = $REV->grade_weight_map();
$tnames = array();
foreach ($wmap as $entries) {
    foreach ($entries as $e) {
        $t = (int) $e['t'];
        if (!isset($tnames[$t])) {
            $tnames[$t] = isset($names[$t]) ? $names[$t] : ('#' . $t);
        }
    }
}

/** هللات ⇐ نص ريالات. الخانتان تظهران حين يكون فيه هللات، فلا يدور مال. */
$money = function ($halalas) {
    $h = (int) $halalas;
    return number_format($h / 100, ($h % 100 === 0) ? 0 : 2) . ' ر.س';
};
?>

<div class="tqa-card tqa-split" style="margin-block-end:var(--tq-space-l)"
     data-tqa-split
     data-tqa-split-default="<?php echo html_escape((string) $default); ?>"
     data-tqa-split-scope="<?php echo html_escape((string) $plan['scope']); ?>"
     data-tqa-split-map="<?php echo html_escape(json_encode($wmap)); ?>"
     data-tqa-split-names="<?php echo html_escape(json_encode($tnames, JSON_UNESCAPED_UNICODE)); ?>">

    <div class="tqa-card__head">
        <div>
            <h2 class="tqa-reach__title">قسمة إيراد هذه الباقة</h2>
            <p class="tqa-reach__lead">
                الوعاء يقسم على المعلمين <b>بعدد دروسهم</b> لا بعدد كورساتهم — فمن وضع
                منهجه في كورس واحد فيه مئة درس لا يأخذ مثل من فرق ثلاثين درسا على ثلاثة
                كورسات. ونسبة كل مسار في شاشة «المسارات التعليمية» تزن دروسه: مسار نسبته
                30 تزن دروسه ضعف مسار نسبته 15.
            </p>
        </div>
    </div>

    <div class="tqa-card__body">

        <?php /* الشطران أولا: هما ما يقرره حقل النسبة مباشرة. */ ?>
        <div class="tqa-split__bar" data-tqa-split-bar>
            <div class="tqa-split__seg tqa-split__seg--platform"
                 style="flex:<?php echo max(1, (int) $split['platform_percent']); ?>"
                 data-tqa-split-seg="platform">
                <b data-tqa-split-n="platform_money"><?php echo $money($split['platform']); ?></b>
                <span>المنصة · <i data-tqa-split-n="platform_pct"><?php echo (float) $split['platform_percent']; ?></i>%</span>
            </div>
            <div class="tqa-split__seg tqa-split__seg--pool"
                 style="flex:<?php echo max(1, (int) $split['pool_percent']); ?>"
                 data-tqa-split-seg="pool">
                <b data-tqa-split-n="pool_money"><?php echo $money($split['pool']); ?></b>
                <span>المعلمون · <i data-tqa-split-n="pool_pct"><?php echo (float) $split['pool_percent']; ?></i>%</span>
            </div>
        </div>

        <p class="tqa-split__basis" data-tqa-split-basis
           <?php echo $is_def ? '' : 'hidden'; ?>>
            هذه الباقة على النسبة الافتراضية العامة
            (<b><?php echo (float) $default; ?>%</b>) — اكتب نسبة في الحقل أعلاه لتخصها وحدها.
        </p>

        <p class="tqa-reach__note" data-tqa-split-noprice
           <?php echo ((int) $split['gross'] <= 0) ? '' : 'hidden'; ?>>
            الباقة بلا سعر بعد، فالأرقام أعلاه أصفار. ضع سعرها لترى القسمة بالريال.
        </p>

        <?php /* ثم من يأخذ ماذا. وهذا ما لا يعرف بحساب ذهني.
                 والحالتان مرسومتان معا وإحداهما مخفية: السكربت يبدل بينهما
                 وأنت تعلم الصفوف، ومن لم يصله الملف يرى الحالة الصحيحة
                 للمحفوظ لا لوحة فارغة. */ ?>
        <div class="tqa-reach__vis tqa-reach__vis--no" data-tqa-split-empty
             <?php echo $split['rows'] ? 'hidden' : ''; ?>>
            <b>لا معلم يستحق</b>
            <span data-tqa-split-empty-why>
                <?php if (!$REV->plan_grade_ids($plan) && $plan['scope'] === 'grade'): ?>
                    اختر صفوف الباقة أولا — القسمة تقرأ مسارات هذه الصفوف.
                <?php else: ?>
                    لا مسار منشور بمعلم مسند في نطاق هذه الباقة. فلو بيعت اليوم لبقي
                    وعاء <?php echo $money($split['pool']); ?> بلا صاحب، واحتفظت
                    به المنصة بلا قرار. أسند معلما لمسارات هذه الصفوف من شاشة
                    «المسارات التعليمية».
                <?php endif; ?>
            </span>
        </div>

        <div data-tqa-split-filled <?php echo $split['rows'] ? '' : 'hidden'; ?>>

            <table class="tqa-split__table" data-tqa-split-table>
                <thead>
                    <tr>
                        <th>المعلم</th>
                        <th>دروسه في الباقة</th>
                        <th>وزنه</th>
                        <th>حصته من كل بيعة</th>
                    </tr>
                </thead>
                <tbody data-tqa-split-body>
                    <?php foreach ($split['rows'] as $tid => $r): ?>
                        <tr data-tqa-split-row="<?php echo (int) $tid; ?>"
                            data-tqa-split-weight="<?php echo (int) $r['weight']; ?>">
                            <td>
                                <b><?php echo html_escape(isset($names[$tid]) ? $names[$tid] : '#' . (int) $tid); ?></b>
                                <span class="tqa-dim">
                                    <?php
                                    $titles = array();
                                    foreach ($r['paths'] as $p) $titles[] = $p['title'];
                                    echo html_escape(implode(' · ', array_slice($titles, 0, 3)));
                                    if (count($titles) > 3) echo ' وغيرها';
                                    ?>
                                </span>
                            </td>
                            <td>
                                <?php echo (int) $r['lessons']; ?>
                                <span class="tqa-dim">من <?php echo (int) $split['lessons_total']; ?></span>
                            </td>
                            <td><?php echo (float) $r['weight_pct']; ?>%</td>
                            <td><b data-tqa-split-share><?php echo $money($r['share']); ?></b></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td>مجموع المعلمين</td>
                        <td data-tqa-split-n="lessons_total"><?php echo (int) $split['lessons_total']; ?> درسا</td>
                        <td>100%</td>
                        <td><b data-tqa-split-n="pool_money2"><?php echo $money($split['pool']); ?></b></td>
                    </tr>
                </tfoot>
            </table>

            <p class="tqa-split__closed">
                مجموع الحصص يساوي الوعاء بالضبط مهما كثر المعلمون — لا هللة تضيع ولا هللة
                تخترع. فدخول معلم ثامن يقسم الوعاء نفسه على ثمانية، ولا يزيد ما تدفعه المنصة.
            </p>

        </div>

        <?php /* المحتوى الذي لا صاحب له: يعلن هنا لا يكتشف من شكوى. */ ?>
        <?php if ((int) $split['orphans']['paths'] > 0): ?>
            <p class="tqa-reach__note">
                و<b><?php echo (int) $split['orphans']['paths']; ?></b> مسارا في نطاق هذه
                الباقة خارج القسمة — بلا معلم مسند أو بلا دورة مرتبطة أو بلا درس منشور.
                <?php if ((int) $split['orphans']['lessons'] > 0): ?>
                    ومنها <b><?php echo (int) $split['orphans']['lessons']; ?></b> درسا يفتحه
                    المشتري ولا يستحق عنه أحد شيئا.
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <?php /* ما وقع فعلا — يفرق بين المعاينة والتاريخ. */ ?>
        <?php if ($rid > 0):
            $sold = $CI->db->query(
                'SELECT COUNT(DISTINCT `subscription_id`) n,
                        COALESCE(SUM(`amount_halalas`),0) s
                   FROM `revenue_shares` WHERE `plan_id` = ?',
                array((int) $rid)
            )->row_array();
        ?>
            <?php if ($sold && (int) $sold['n'] > 0): ?>
                <p class="tqa-split__sold">
                    بيعت هذه الباقة <b><?php echo (int) $sold['n']; ?></b> مرة، ووزع عن مبيعاتها
                    <b><?php echo $money((int) $sold['s']); ?></b> على معلميها.
                    وتعديل النسبة الآن يخص ما يباع بعده وحده — القسمة تجمد وقت التفعيل،
                    فمن اشترك أمس قسم ماله على نسبة أمس.
                </p>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</div>
