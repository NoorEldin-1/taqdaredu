<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * شاشة الربط — أسئلة اختبارات الدورة كلها، وأهداف دروسها كلها.
 *
 * تجمع الأهداف بدرسها وتعرض في القائمة داخل `optgroup`: الهدف بلا درسه
 * نص معلق، والمربي يحتاج أن يرى إلى أي فيديو سيعيد السؤال الطالب.
 */

$obj_by_lesson = array();   // lesson_id => ['title'=>..,'items'=>[..]]
foreach ($objectives as $o) {
    $lid = (int) $o['lesson_id'];
    if (!isset($obj_by_lesson[$lid])) {
        $obj_by_lesson[$lid] = array('title' => $o['lesson_title'], 'items' => array());
    }
    $obj_by_lesson[$lid]['items'][] = $o;
}

$q_by_quiz = array();       // quiz_id => ['title'=>..,'items'=>[..]]
foreach ($questions as $q) {
    $qid = (int) $q['quiz_id'];
    if (!isset($q_by_quiz[$qid])) {
        $q_by_quiz[$qid] = array('title' => $q['quiz_title'], 'items' => array());
    }
    $q_by_quiz[$qid]['items'][] = $q;
}

/** الثواني تقرأ دقائق وثوان — «٧٥ ثانية» لا تدل على موضع في شريط الفيديو. */
if (!function_exists('tqa_mmss')) {
    function tqa_mmss($sec)
    {
        $sec = (int) $sec;
        return sprintf('%02d:%02d', intdiv($sec, 60), $sec % 60);
    }
}

$bound_now = 0;
$broken    = 0;
foreach ($questions as $q) {
    $oid = (int) $q['objective_id'];
    if (!$oid) continue;
    if ((int) $q['objective_course_id'] === (int) $course['id']) $bound_now++;
    else $broken++;
}
?>

<div class="tqa-head">
    <div>
        <h1>ربط أسئلة: <?php echo html_escape($course['title']); ?></h1>
        <p>لكل سؤال هدف واحد — هو ما يحدد إلى أين يعاد الطالب حين يخطئ.</p>
    </div>
    <a class="btn btn-secondary" href="<?php echo site_url('taqdar_admin/bindings'); ?>">رجوع</a>
</div>

<?php tqa_flash(); ?>

<?php if (empty($objectives)): ?>

    <div class="tqa-block">
        <h3>هذه الدورة بلا أهداف</h3>
        <p>
            أضف أهدافا لدروس الفيديو فيها أولا، فالربط يحتاج طرفين.
            والهدف يعلق على درس الفيديو لا على درس الاختبار: إليه يعيد النظام
            الطالب، وعند لحظته من الشرح تحديدا.
        </p>
        <a class="btn btn-primary" href="<?php echo site_url('taqdar_admin/form/objectives'); ?>">أضف هدفا</a>
    </div>

<?php elseif (empty($questions)): ?>

    <div class="tqa-empty">
        <h3>لا أسئلة في اختبارات هذه الدورة</h3>
        <p>أضف درس اختبار وأسئلته من شاشة الدورات، ثم عد إلى هنا لتربطها بالأهداف.</p>
    </div>

<?php else: ?>

    <div class="card">
        <div class="card-header">
            <h4 class="header-title">أهداف دروس هذه الدورة</h4>
        </div>
        <div class="card-body">
            <?php foreach ($obj_by_lesson as $lid => $g): ?>
                <div class="tqa-field">
                    <label><?php echo html_escape($g['title']); ?></label>
                    <div class="tqa-objlist">
                        <?php foreach ($g['items'] as $o): ?>
                            <span class="tqa-chip">
                                <?php echo html_escape($o['text']); ?>
                                <span class="tq-ltr" dir="ltr">— <?php echo tqa_mmss($o['at_second']); ?></span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($broken): ?>
        <div class="tqa-note">
            <strong>تنبيه:</strong>
            <span class="tq-ltr" dir="ltr"><?php echo $broken; ?></span>
            سؤالا مربوط بهدف خارج هذه الدورة أو بهدف محذوف — والبوابة لا تراه.
            هذه الأسئلة تظهر أدناه «بلا هدف»، فاختر لها هدفا صحيحا قبل الحفظ،
            وإلا ألغي ربطها المكسور.
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo site_url('taqdar_admin/bind_save/' . (int) $course['id']); ?>">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th style="inline-size:56px">#</th>
                                <th>السؤال</th>
                                <th style="inline-size:340px">الهدف</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $n = 0; foreach ($q_by_quiz as $qid => $grp): ?>
                            <tr>
                                <td colspan="3">
                                    <strong><?php echo html_escape($grp['title']); ?></strong>
                                    <span class="tqa-dim">
                                        — اختبار،
                                        <span class="tq-ltr" dir="ltr"><?php echo count($grp['items']); ?></span>
                                        سؤالا
                                    </span>
                                </td>
                            </tr>
                            <?php foreach ($grp['items'] as $q):
                                $n++;
                                $cur     = (int) $q['objective_id'];
                                $in_this = $cur && ((int) $q['objective_course_id'] === (int) $course['id']);
                            ?>
                            <tr>
                                <td><span class="tq-ltr" dir="ltr"><?php echo $n; ?></span></td>
                                <td>
                                    <?php echo html_escape(strip_tags($q['title'])); ?>
                                    <?php if ($cur && !$in_this): ?>
                                        <span class="tqa-warn">ربطه الحالي خارج هذه الدورة — يلغى ما لم تختر هدفا.</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <select class="form-control" name="objective[<?php echo (int) $q['id']; ?>]">
                                        <option value="0">— بلا هدف —</option>
                                        <?php foreach ($obj_by_lesson as $lid => $g): ?>
                                            <optgroup label="<?php echo html_escape($g['title']); ?>">
                                                <?php foreach ($g['items'] as $o): ?>
                                                    <option value="<?php echo (int) $o['id']; ?>"
                                                        <?php echo ($in_this && $cur === (int) $o['id']) ? 'selected' : ''; ?>>
                                                        <?php echo html_escape($o['text']); ?>
                                                        — <?php echo tqa_mmss($o['at_second']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <p class="tqa-count">
                    مربوط الآن <span class="tq-ltr" dir="ltr"><?php echo $bound_now; ?></span>
                    من <span class="tq-ltr" dir="ltr"><?php echo count($questions); ?></span> سؤالا.
                    والقائمة تعرض أهداف هذه الدورة وحدها؛ وما وصل من خارجها يرفض في الخادم
                    ولو غيرت القائمة في المتصفح.
                </p>

                <div class="tqa-actions">
                    <button type="submit" class="btn btn-primary">حفظ الربط</button>
                    <a class="btn btn-secondary" href="<?php echo site_url('taqdar_admin/bindings'); ?>">رجوع</a>
                </div>
            </div>
        </div>
    </form>

<?php endif; ?>
