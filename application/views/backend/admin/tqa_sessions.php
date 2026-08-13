<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * الحصص بالطلب.
 *
 * دورة الحصة: المعلم يفتح وقتا (`availability_slots`) · الطالب يحجزه
 * فينشأ `tutoring_sessions` بحالة `requested` · المعلم يقبل أو يرفض.
 * ولم يكن في اللوحة شاشة واحدة تراها — فالإدارة تعرف بالشكوى وحدها،
 * وطلب علق أسبوعا لا يظهر في مكان.
 */

$labels = array(
    'requested' => 'بانتظار رد المعلم',
    'confirmed' => 'مؤكدة',
    'live'      => 'جارية الآن',
    'completed' => 'انتهت',
    'declined'  => 'رفضت أو ألغيت',
    'expired'   => 'فات موعدها',
    'refunded'  => 'استردت',
);
$tones = array(
    'requested' => 'warn', 'confirmed' => 'ok',    'live' => 'info',
    'completed' => 'muted','declined'  => 'danger','expired' => 'muted', 'refunded' => 'danger',
);
?>

<?php tqa_head('الحصص', 'كل حصة طلبت، ومن طلبها، ومن يدرسها، وأين وقفت.', 'video'); ?>

<?php /* المرشحات: العدد جزء من التسمية — «بانتظار رد» بلا رقم لا تخبر
         إن كان الانتظار واحدا أو أربعين. */ ?>
<div class="tqa-tabs">
    <a href="<?php echo site_url('taqdar_admin/sessions'); ?>"
       <?php echo $status === '' ? 'aria-current="page"' : ''; ?>>الكل</a>
    <?php foreach ($labels as $k => $label): ?>
        <a href="<?php echo site_url('taqdar_admin/sessions?status=' . $k); ?>"
           <?php echo $status === $k ? 'aria-current="page"' : ''; ?>>
            <?php echo html_escape($label); ?>
            <?php if (!empty($tally[$k])): ?>
                <span class="tqa-num">(<?php echo (int) $tally[$k]; ?>)</span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="tqa-card tqa-card--flush">
<?php if (!$rows): ?>

    <?php tqa_empty(
        $status === '' ? 'لا حصص بعد' : 'لا حصة في هذه الحالة',
        $status === ''
            ? 'الحصة تبدأ حين يفتح معلم وقتا في شاشة «الحصص» ببوابته، ثم يحجزه طالب. فإن لم يفتح أحد وقتا فلا حصة تطلب — راجع أوقات المعلمين.'
            : 'جرب مرشحا آخر، أو اعرض الكل.',
        'أوقات المعلمين', site_url('taqdar_admin/slots'), 'video'
    ); ?>

<?php else: ?>
    <div class="tqa-table__wrap">
    <table class="tqa-table">
        <thead>
            <tr>
                <th>الموعد</th>
                <th>الطالب</th>
                <th>المعلم</th>
                <th>سبب الحصة</th>
                <th>الحالة</th>
                <th>الرابط</th>
                <th><span class="tqa-sr">إجراء</span></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
            $st   = (string) $r['status'];
            $tone = $tones[$st] ?? 'muted';
            $when = !empty($r['starts_at']) ? strtotime($r['starts_at']) : 0;
            /* الحصة الملغاة أو المنتهية لا تلغى ثانية: زر يعتذر أسوأ من زر غائب. */
            $can_cancel = !in_array($st, array('completed', 'declined', 'refunded'), true);
        ?>
            <tr>
                <td data-label="الموعد">
                    <?php if ($when): ?>
                        <span class="tqa-num"><?php echo date('Y-m-d', $when); ?></span><br>
                        <span class="tqa-num" style="color:var(--tq-text2)"><?php echo date('H:i', $when); ?></span>
                        <?php if (!empty($r['duration_min'])): ?>
                            <span style="color:var(--tq-text2)">·
                                <span class="tqa-num"><?php echo (int) $r['duration_min']; ?></span> دقيقة</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color:var(--tq-text3)">الفسحة حذفت</span>
                    <?php endif; ?>
                </td>
                <td data-label="الطالب">
                    <?php echo html_escape($r['student_name'] ?: '—'); ?><br>
                    <span class="tqa-num" style="color:var(--tq-text2);font-size:12px">
                        <?php echo html_escape($r['student_email'] ?: ''); ?></span>
                </td>
                <td data-label="المعلم"><?php echo html_escape($r['teacher_name'] ?: '—'); ?></td>
                <td data-label="سبب الحصة">
                    <?php if (!empty($r['objective_text'])): ?>
                        <?php echo html_escape(mb_strimwidth($r['objective_text'], 0, 60, '…', 'UTF-8')); ?>
                    <?php else: ?>
                        <span style="color:var(--tq-text3)">حصة عامة</span>
                    <?php endif; ?>
                </td>
                <td data-label="الحالة">
                    <span class="tqa-badge tqa-badge--<?php echo $tone; ?>">
                        <?php echo html_escape($labels[$st] ?? $st); ?>
                    </span>
                </td>
                <td data-label="الرابط">
                    <?php if (!empty($r['room_id'])): ?>
                        <a href="<?php echo html_escape($r['room_id']); ?>" target="_blank" rel="noopener">افتح</a>
                    <?php else: ?>
                        <span style="color:var(--tq-text3)">—</span>
                    <?php endif; ?>
                </td>
                <td data-label="إجراء">
                    <?php if ($can_cancel): ?>
                        <?php /* الإلغاء POST لا رابط: يكتب في القاعدة ويحرر وقتا،
                                 ورابط يفعل ذلك بمجرد فتحه يستدعيه استباق المتصفح. */ ?>
                        <form action="<?php echo site_url('taqdar_admin/session_cancel'); ?>" method="post"
                              data-tqa-confirm-title="إلغاء الحصة"
                              data-tqa-confirm="سيحرر وقتها ويخطر الطالب والمعلم."
                              data-tqa-confirm-ok="ألغ الحصة"
                              data-tqa-confirm-tone="danger"
                              style="margin:0;display:flex;gap:6px;align-items:center">
                            <?php echo tq_csrf(); ?>
                            <input type="hidden" name="session_id" value="<?php echo (int) $r['id']; ?>">
                            <input class="tqa-input tqa-btn--sm" type="text" name="reason"
                                   placeholder="السبب (اختياري)" style="min-block-size:34px;inline-size:150px">
                            <button class="tqa-btn tqa-btn--ghost tqa-btn--sm" type="submit">إلغاء</button>
                        </form>
                    <?php else: ?>
                        <span style="color:var(--tq-text3)">—</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
</div>
