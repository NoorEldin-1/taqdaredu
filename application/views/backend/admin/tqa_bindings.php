<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * نظرة عامة على الربط — بالدورة لا بالدرس.
 *
 * السؤال يسكن درس الاختبار، والهدف يسكن درس الفيديو، وهما درسان مختلفان
 * في أي دورة حقيقية. فالوحدة التي يجتمع فيها الطرفان هي الدورة، وعليها
 * تحسب الأرقام هنا وإليها يقود زر الربط.
 */
?>

<div class="tqa-head">
    <div>
        <h1>ربط الأسئلة بالأهداف</h1>
        <p>الدورات مرتبة بالأكثر نقصا أولا — ابدأ من أعلى القائمة.</p>
    </div>
</div>

<?php tqa_flash(); ?>

<div class="tqa-note">
    <strong>السؤال المربوط بهدف هو وحده ما تراه بوابة الإتقان.</strong>
    فالمحرك يقرأ: سؤال ← هدف ← درس الفيديو. ودورة بلا سؤال مربوط لا ينشأ
    فيها تقييم مراجعة أصلا، فينحدر شرط الفتح إلى المشاهدة وحدها ويصير القفل
    قفل مشاهدة لا قفل إتقان.
    والأهداف تضاف من
    <a href="<?php echo site_url('taqdar_admin/module/objectives'); ?>">شاشة الأهداف</a>
    على دروس الفيديو، لا على دروس الاختبار.
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($courses)): ?>

            <div class="tqa-empty">
                <h3>لا توجد دورة فيها أسئلة أو أهداف بعد</h3>
                <p>أضف دورة ودروسها واختباراتها، ثم أهدافا لدروس الفيديو، ثم عد إلى هنا.</p>
            </div>

        <?php else: ?>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>الدورة</th>
                            <th>الاختبارات</th>
                            <th>الأسئلة</th>
                            <th>المربوط</th>
                            <th>دروس بأهداف</th>
                            <th>الأهداف</th>
                            <th>الحالة</th>
                            <th style="inline-size:130px"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($courses as $c):
                        $t  = (int) $c['total_q'];
                        $b  = (int) $c['bound_q'];
                        $o  = (int) $c['objectives'];
                        $ol = (int) $c['objective_lessons'];
                        $qz = (int) $c['quiz_lessons'];

                        if ($o === 0)      { $tone = 'danger';  $txt = 'بلا أهداف'; }
                        elseif ($t === 0)  { $tone = 'warning'; $txt = 'بلا أسئلة'; }
                        elseif ($b === 0)  { $tone = 'danger';  $txt = 'غير مربوط'; }
                        elseif ($b < $t)   { $tone = 'warning'; $txt = 'ناقص'; }
                        else               { $tone = 'success'; $txt = 'مكتمل'; }
                    ?>
                        <tr>
                            <td><?php echo html_escape($c['course_title']); ?></td>
                            <td><span class="tq-ltr" dir="ltr"><?php echo $qz; ?></span></td>
                            <td><span class="tq-ltr" dir="ltr"><?php echo $t; ?></span></td>
                            <td><span class="tq-ltr" dir="ltr"><?php echo $b; ?></span></td>
                            <td><span class="tq-ltr" dir="ltr"><?php echo $ol; ?></span></td>
                            <td><span class="tq-ltr" dir="ltr"><?php echo $o; ?></span></td>
                            <td><span class="badge badge-<?php echo $tone; ?>"><?php echo $txt; ?></span></td>
                            <td>
                                <?php if ($o === 0): ?>
                                    <a class="btn btn-sm btn-secondary"
                                       href="<?php echo site_url('taqdar_admin/form/objectives'); ?>">أضف أهدافا</a>
                                <?php elseif ($t === 0): ?>
                                    <span class="tqa-dim">لا أسئلة تربط</span>
                                <?php else: ?>
                                    <a class="btn btn-sm btn-primary"
                                       href="<?php echo site_url('taqdar_admin/bind/' . (int) $c['course_id']); ?>">اربط</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p class="tqa-count">
                «المربوط» يحسب بشرط المحرك نفسه: سؤال يشير إلى هدف قائم في
                إحدى دروس هذه الدورة. أما سؤال يشير إلى هدف محذوف أو إلى هدف في
                دورة أخرى فلا يعد مربوطا — لأن البوابة لا تجده.
            </p>

        <?php endif; ?>
    </div>
</div>
