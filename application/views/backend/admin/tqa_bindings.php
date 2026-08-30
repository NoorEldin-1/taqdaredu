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
        <h1><?php echo t('ربط الأسئلة بالأهداف'); ?></h1>
        <p><?php echo t('الدورات مرتبة بالأكثر نقصا أولا — ابدأ من أعلى القائمة.'); ?></p>
    </div>
</div>


<div class="tqa-note">
    <strong><?php echo t('السؤال المربوط بهدف هو وحده ما تراه بوابة الإتقان.'); ?></strong>
    <?php echo t('فالمحرك يقرأ: سؤال ← هدف ← درس الفيديو. ودورة بلا سؤال مربوط لا ينشأ فيها تقييم مراجعة أصلا، فينحدر شرط الفتح إلى المشاهدة وحدها ويصير القفل قفل مشاهدة لا قفل إتقان. والأهداف تضاف من'); ?>
    <a href="<?php echo site_url('taqdar_admin/module/objectives'); ?>"><?php echo t('شاشة الأهداف'); ?></a>
    <?php echo t('على دروس الفيديو، لا على دروس الاختبار.'); ?>
</div>

<div class="tqa-card">
    <div class="tqa-card__body">
        <?php if (empty($courses)): ?>

            <div class="tqa-empty">
                <h3><?php echo t('لا توجد دورة فيها أسئلة أو أهداف بعد'); ?></h3>
                <p><?php echo t('أضف دورة ودروسها واختباراتها، ثم أهدافا لدروس الفيديو، ثم عد إلى هنا.'); ?></p>
            </div>

        <?php else: ?>

            <div class="tqa-table__wrap">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th><?php echo t('الدورة'); ?></th>
                            <th><?php echo t('الاختبارات'); ?></th>
                            <th><?php echo t('الأسئلة'); ?></th>
                            <th><?php echo t('المربوط'); ?></th>
                            <th><?php echo t('دروس بأهداف'); ?></th>
                            <th><?php echo t('الأهداف'); ?></th>
                            <th><?php echo t('الحالة'); ?></th>
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

                        if ($o === 0)      { $tone = 'danger';  $txt = t('بلا أهداف'); }
                        elseif ($t === 0)  { $tone = 'warning'; $txt = t('بلا أسئلة'); }
                        elseif ($b === 0)  { $tone = 'danger';  $txt = t('غير مربوط'); }
                        elseif ($b < $t)   { $tone = 'warning'; $txt = t('ناقص'); }
                        else               { $tone = 'success'; $txt = t('مكتمل'); }
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
                                    <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                                       href="<?php echo site_url('taqdar_admin/form/objectives'); ?>"><?php echo t('أضف أهدافا'); ?></a>
                                <?php elseif ($t === 0): ?>
                                    <span class="tqa-dim"><?php echo t('لا أسئلة تربط'); ?></span>
                                <?php else: ?>
                                    <a class="tqa-btn tqa-btn--primary tqa-btn--sm"
                                       href="<?php echo site_url('taqdar_admin/bind/' . (int) $c['course_id']); ?>"><?php echo t('اربط'); ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p class="tqa-count">
                <?php echo t('«المربوط» يحسب بشرط المحرك نفسه: سؤال يشير إلى هدف قائم في إحدى دروس هذه الدورة. أما سؤال يشير إلى هدف محذوف أو إلى هدف في دورة أخرى فلا يعد مربوطا — لأن البوابة لا تجده.'); ?>
            </p>

        <?php endif; ?>
    </div>
</div>
