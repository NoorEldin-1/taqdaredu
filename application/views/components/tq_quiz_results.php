<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * نتائج اختبارات الدروس — لوح واحد يقرؤه أربعة.
 *
 * الطلب: «نتايج الاختبارات دي طبعا بتكون موجودة عند الطالب وولي أمره
 * وطبعا المدرس وفي الادمن».
 *
 * وكانت النتيجة في مكانين لا يعرف أحدهما الآخر: `quiz_results` الموروث
 * تقرؤه شاشة اختبارات الطالب وتقارير ولي الأمر، و`attempts` تقرؤه صفحة
 * الدرس ودفتر الأخطاء وخريطة الإتقان. فيسأل ولي الأمر «كيف حال ابني؟»
 * فيقرأ نصف الحقيقة.
 *
 * وهذا اللوح يقرأ `attempts` — حيث تعيش نتائج بوابة الإتقان، وهي التي
 * صار اختبار الدرس يكتب فيها.
 *
 * ----------------------------------------------------------------------
 * المتغيرات:
 *   $r_rows   من `Taqdar_quiz_model::student_results()`
 *   $r_skin   'tq' | 'tqa'
 *   $r_title  عنوان اللوح
 *   $r_empty  ما يقال حين لا نتيجة
 *   $r_who    'student' | 'parent' | 'teacher' — يغير الخطاب لا البيانات
 * ----------------------------------------------------------------------
 *
 * **آخر محاولة لكل اختبار لا كل المحاولات**: السؤال «أين هو الآن؟» لا
 * «كم مرة حاول؟». وعدد المحاولات يخرج بجوارها، لأن الطالب الذي اجتاز من
 * الرابعة ليس كالذي اجتاز من الأولى — والفرق يعني درسا يحتاج إعادة شرح.
 */

$r_rows  = isset($r_rows)  ? $r_rows  : array();
$r_skin  = isset($r_skin)  ? $r_skin  : 'tq';
$r_who   = isset($r_who)   ? $r_who   : 'student';
$r_title = isset($r_title) ? $r_title : 'نتائج اختبارات الدروس';
$r_empty = isset($r_empty) ? $r_empty : 'لا نتائج بعد.';

$rq = ($r_skin === 'tqa');
?>

<style>
.tqr-res      { inline-size: 100%; border-collapse: collapse; }
.tqr-res th, .tqr-res td { text-align: start; padding: var(--tq-space-s) var(--tq-space-m);
                           border-block-end: 1px solid var(--tq-line); font: var(--tq-type-caption); }
.tqr-res th   { color: var(--tq-text3); font-weight: 700; }
.tqr-res__l   { font-weight: 700; color: var(--tq-text); }
.tqr-res__c   { display: block; color: var(--tq-text3); font: var(--tq-type-micro); }
.tqr-res__n   { font-variant-numeric: tabular-nums; unicode-bidi: isolate; }
.tqr-pass     { color: var(--tq-teal); font-weight: 700; }
.tqr-fail     { color: var(--tq-danger); font-weight: 700; }
.tqr-tries    { color: var(--tq-text3); font: var(--tq-type-micro); }
.tqr-wrap     { overflow-x: auto; }
</style>

<div class="<?php echo $rq ? 'tqa-card' : 'tq-card'; ?>">
    <div class="<?php echo $rq ? 'tqa-card__head' : 'tq-card__head'; ?>">
        <?php if ($rq): ?>
            <h4 class="header-title"><?php echo html_escape($r_title); ?></h4>
        <?php else: ?>
            <h2 class="tq-card__title"><?php echo html_escape($r_title); ?></h2>
        <?php endif; ?>
    </div>

    <div class="<?php echo $rq ? 'tqa-card__body' : ''; ?>">
    <?php if (empty($r_rows)): ?>

        <p class="<?php echo $rq ? 'tqa-dim' : 'tq-caption'; ?>"><?php echo html_escape($r_empty); ?></p>

    <?php else: ?>

        <p class="<?php echo $rq ? 'tqa-dim' : 'tq-caption'; ?>"
           style="margin-block-end:var(--tq-space-m)">
            <?php
            echo $r_who === 'parent'
                ? 'آخر نتيجة في كل درس. واختبار الدرس هو ما يفتح الدرس الذي بعده، فالدرس الذي لم يجتز يقف عنده الطريق.'
                : ($r_who === 'teacher'
                    ? 'آخر نتيجة لكل طالب في كل درس. وعدد المحاولات يقرأ عن الشرح: من اجتاز من الرابعة لم يفهم من الأولى.'
                    : 'آخر نتيجة في كل درس. وباجتياز اختبار الدرس يفتح الدرس الذي بعده.');
            ?>
        </p>

        <div class="tqr-wrap">
        <table class="tqr-res">
            <thead>
                <tr>
                    <th scope="col">الدرس</th>
                    <th scope="col">الصحيح</th>
                    <th scope="col">النتيجة</th>
                    <th scope="col">المحاولات</th>
                    <th scope="col">التاريخ</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($r_rows as $r):
                $pass  = (int) $r['passed'] === 1;
                $score = (int) $r['score'];
                $total = (int) $r['total'];
                $tries = (int) $r['tries'];
            ?>
                <tr>
                    <td>
                        <span class="tqr-res__l"><?php echo html_escape($r['lesson_title']); ?></span>
                        <?php if (!empty($r['course_title'])): ?>
                            <span class="tqr-res__c"><?php echo html_escape($r['course_title']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="tqr-res__n">
                        <?php echo tq_iso($score); ?> من <?php echo tq_iso($total ?: (int) $r['pass_mark']); ?>
                    </td>
                    <td>
                        <span class="<?php echo $pass ? 'tqr-pass' : 'tqr-fail'; ?>">
                            <?php echo $pass ? 'اجتاز' : 'لم يجتز بعد'; ?>
                        </span>
                    </td>
                    <td>
                        <span class="tqr-res__n"><?php echo tq_iso($tries); ?></span>
                        <?php if (!$pass): ?>
                            <span class="tqr-tries" style="display:block">
                                <?php echo $r_who === 'student'
                                    ? 'أعد المحاولة — لا حد لعددها'
                                    : 'الدرس التالي مقفل'; ?>
                            </span>
                        <?php elseif ($tries >= 3): ?>
                            <span class="tqr-tries" style="display:block">
                                <?php echo $r_who === 'student'
                                    ? 'اجتزته بعد محاولات — راجعه ثانية'
                                    : 'اجتاز بعد محاولات — يستحق إعادة شرح'; ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="tqr-res__n" dir="ltr">
                        <?php echo html_escape(substr((string) $r['submitted_at'], 0, 16)); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

    <?php endif; ?>
    </div>
</div>
