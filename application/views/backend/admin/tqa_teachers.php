<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/**
 * اعتماد المعلمين.
 *
 * من يتقدم معلما **لا يفتح له حساب** — ينشأ طلب ينتظر مراجعتك.
 * ومنصة يدرس فيها الغرباء بلا تحقق ليست منصة تعليمية.
 * والرفض يسجل كما يسجل القبول: قرار بلا أثر لا يراجع ولا يفسر.
 *
 * أعيدت كتابة العرض لأربعة أعطال كلها ظاهرة في الشاشة:
 *
 * ١ — **خمسة أصناف بلا تعريف**: `tqa-wrap` · `tqa-lead` · `tqa-pill`
 *     (وتنويعتاها) · `tqa-inline`. فالحالة تخرج نصا عاريا لا شارة،
 *     والنموذجان — و`<form>` عنصر كتلة — يقفان سطرين فيظهر «اعتماد»
 *     فوق «رفض» بمحاذاة لا تشبه شيئا في اللوحة.
 *     (عرفت الأصناف في `admin.css`، وتكتب هنا بأسماء النظام الجديد.)
 *
 * ٢ — **بلا توكن CSRF**. الحماية مفعلة، والحقن العام يعمل بجافاسكربت
 *     وحده: تعثر ملف واحد يجعل كل اعتماد ورفض يرد 403.
 *
 * ٣ — **بلا تأكيد**. الاعتماد يفتح حسابا لغريب يدرس قاصرين، والرفض
 *     يغلق حساب من انتظر أياما — وكلاهما ضغطة واحدة بلا سؤال.
 *
 * ٤ — **الحساب المحذوف يخرج صفا فارغا**. الضم `left` يرد `null` في
 *     أعمدة المستخدم، فيعرض صف بلا اسم ولا بريد وبزري قرار يعملان —
 *     يعتمد المراجع حسابا لا وجود له فلا يحدث شيء ولا يقال لماذا.
 */
$tq_states = array(
    0 => array('',      t('بانتظار المراجعة')),
    1 => array('--ok',  t('معتمد')),
    2 => array('--no',  t('مرفوض')),
);

$tq_pending = 0;
foreach ($apps as $tq_a) { if ((int) $tq_a['status'] === 0) $tq_pending++; }
?>

<?php tqa_head(
    t('طلبات المعلمين'),
    t('الاعتماد يفتح حساب المعلم ولوحته؛ وقبله لا يستطيع الدخول.'),
    'file',
    $tq_pending
        ? '<span class="tqa-badge tqa-badge--warn"><span class="tqa-num">' . $tq_pending . t('</span> ينتظر قرارك</span>')
        : t('<span class="tqa-badge tqa-badge--ok">لا طلب معلق</span>')
); ?>

<div class="tqa-card tqa-card--flush">
<?php if (empty($apps)): ?>

    <?php tqa_empty(
        t('لا طلبات انضمام بعد'),
        t('الطلب ينشأ حين يختار أحدهم بوابة «معلم» في صفحة إنشاء الحساب ويرفق مستند تعريفه.')
        . t('وإن كان التسجيل معلما موقوفا في إعدادات المنصة فلا تصل طلبات أصلا.'),
        '', '', 'file'
    ); ?>

<?php else: ?>
    <div class="tqa-table__wrap">
    <table class="tqa-table">
        <thead>
            <tr>
                <th><?php echo t('المعلم'); ?></th>
                <th><?php echo t('البريد'); ?></th>
                <th><?php echo t('الجوال'); ?></th>
                <th><?php echo t('النبذة'); ?></th>
                <th><?php echo t('المستند والعينة'); ?></th>
                <th><?php echo t('الهوية'); ?></th>
                <th><?php echo t('الحالة'); ?></th>
                <th><span class="tqa-sr"><?php echo t('الإجراء'); ?></span></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($apps as $a):
            $st   = (int) $a['status'];
            list($tone, $label) = isset($tq_states[$st]) ? $tq_states[$st] : array('', t('غير معروفة'));

            $name = trim((string) $a['first_name'] . ' ' . (string) $a['last_name']);
            /* الحساب المحذوف: كل أعمدة الضم فارغة. يقال ذلك ولا يعرض
               زر قرار على صف لا صاحب له. */
            $orphan = ($name === '' && empty($a['email']));
        ?>
            <tr>
                <td data-label="المعلم">
                    <?php if ($orphan): ?>
                        <span class="tqa-dim"><?php echo t('حساب محذوف'); ?></span>
                    <?php else: ?>
                        <strong><?php echo html_escape($name ?: t('بلا اسم')); ?></strong>
                    <?php endif; ?>
                </td>

                <td data-label="البريد">
                    <?php if (!empty($a['email'])): ?>
                        <a class="tqa-num" style="font-size:13px"
                           href="mailto:<?php echo html_escape($a['email']); ?>"
                           dir="ltr"><?php echo html_escape($a['email']); ?></a>
                    <?php else: ?><span class="tqa-dim">—</span><?php endif; ?>
                </td>

                <td data-label="الجوال">
                    <?php if (!empty($a['phone'])): ?>
                        <span class="tqa-num" dir="ltr"><?php echo html_escape($a['phone']); ?></span>
                    <?php else: ?><span class="tqa-dim">—</span><?php endif; ?>
                </td>

                <td data-label="النبذة">
                    <?php
                    $msg = trim((string) $a['message']);
                    if ($msg === '') {
                        echo '<span class="tqa-dim">—</span>';
                    } else {
                        /* النص كامل في `title`: القص إلى تسعين محرفا يخفي
                           آخر ما كتبه المتقدم، وهو غالبا موضع تخصصه. */
                        echo '<span title="' . html_escape($msg) . '">'
                           . html_escape(mb_strimwidth($msg, 0, 90, '…', 'UTF-8')) . '</span>';
                    }
                    ?>
                </td>

                <td data-label="المستند والعينة">
                    <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                    <?php if (!empty($a['document'])): ?>
                        <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                           href="<?php echo base_url('uploads/document/' . rawurlencode($a['document'])); ?>"
                           target="_blank" rel="noopener">
                            <?php echo tq_icon('eye', 15); ?> المؤهل
                        </a>
                    <?php else: ?>
                        <?php /* الطلب بلا مرفق قائم ويصح اعتماده — ولكن يقال
                                 إنه بلا مرفق، لا يترك شرطة تقرأ «لم يفتح». */ ?>
                        <span class="tqa-badge tqa-badge--warn"><?php echo t('بلا مستند'); ?></span>
                    <?php endif; ?>

                    <?php /* عينة الشرح — أهم ما تحكم عليه قبل الاعتماد.
                             وطلب قديم قدم قبل أن يوجد الحقل يقال عنه ذلك
                             صراحة بدل أن يقرأ «رفض إرسالها». */ ?>
                    <?php if (!empty($a['sample_url'])): ?>
                        <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                           href="<?php echo html_escape($a['sample_url']); ?>"
                           target="_blank" rel="noopener">
                            <?php echo tq_icon('play', 15); ?> العينة
                        </a>
                    <?php else: ?>
                        <span class="tqa-badge tqa-badge--warn"><?php echo t('بلا عينة'); ?></span>
                    <?php endif; ?>
                    </div>

                    <?php if (!empty($a['subject_hint'])): ?>
                        <div class="tqa-dim" style="margin-block-start:4px;font-size:.8em"><?php
                            echo html_escape($a['subject_hint']); ?></div>
                    <?php endif; ?>
                </td>

                <td data-label="الهوية">
                    <?php if ($st !== 0 || $orphan): ?>
                        <span class="tqa-dim">—</span>
                    <?php else: ?>
                        <form method="post" style="margin:0"
                              action="<?php echo site_url('taqdar_admin/teacher_identity'); ?>">
                            <?php echo tq_csrf(); ?>
                            <input type="hidden" name="app_id" value="<?php echo (int) $a['id']; ?>">
                            <input type="hidden" name="identity_ok"
                                   value="<?php echo (int) $a['identity_ok'] === 1 ? '0' : '1'; ?>">
                            <button class="tqa-btn tqa-btn--<?php
                                echo (int) $a['identity_ok'] === 1 ? 'ghost' : 'secondary'; ?> tqa-btn--sm"
                                    type="submit"><?php
                                echo (int) $a['identity_ok'] === 1 ? t('موثقة — ألغ') : t('وثق الهوية'); ?></button>
                        </form>
                    <?php endif; ?>
                </td>

                <td data-label="الحالة">
                    <span class="tqa-badge tqa-badge<?php echo $tone === '--ok' ? '--ok'
                        : ($tone === '--no' ? '--danger' : '--warn'); ?>"><?php echo $label; ?></span>
                </td>

                <td data-label="الإجراء">
                    <?php if ($st !== 0 || $orphan): ?>
                        <span class="tqa-dim">—</span>
                    <?php else: ?>
                        <?php /* زر الاعتماد يعطل قبل توثيق الهوية — والحارس
                                 الفعلي في المتحكم، وهذا لئلا يضغط المسؤول زرا
                                 يرد عليه. */ ?>
                        <?php
                        $tq_ready = (int) $a['identity_ok'] === 1;
                        $tq_why = $tq_ready ? '' : t('ينتظر توثيق الهوية');
                        ?>
                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            <form method="post" style="margin:0"
                                  action="<?php echo site_url('taqdar_admin/teacher_review'); ?>"
                                  data-tqa-confirm-title="<?php echo te('اعتماد المعلم'); ?>"
                                  data-tqa-confirm="سيفتح حساب <?php echo html_escape($name ?: t('هذا المتقدم')); ?> ولوحته، ويصير بإمكانه رفع الدروس والتدريس. راجع مستنده أولا."
                                  data-tqa-confirm-ok="اعتمد">
                                <?php echo tq_csrf(); ?>
                                <input type="hidden" name="app_id" value="<?php echo (int) $a['id']; ?>">
                                <input type="hidden" name="act" value="approve">
                                <button type="submit" class="tqa-btn tqa-btn--primary tqa-btn--sm"
                                        <?php echo $tq_ready ? '' : 'disabled'; ?>
                                        title="<?php echo html_escape($tq_why); ?>">
                                    <?php echo tq_icon('check', 15); ?> اعتماد
                                </button>
                            </form>

                            <form method="post" style="margin:0"
                                  action="<?php echo site_url('taqdar_admin/teacher_review'); ?>"
                                  data-tqa-confirm-title="<?php echo te('رفض الطلب'); ?>"
                                  data-tqa-confirm="يبقى الحساب مغلقا ولا يستطيع صاحبه الدخول. ويسجل القرار باسمك."
                                  data-tqa-confirm-ok="ارفض الطلب"
                                  data-tqa-confirm-tone="danger">
                                <?php echo tq_csrf(); ?>
                                <input type="hidden" name="app_id" value="<?php echo (int) $a['id']; ?>">
                                <input type="hidden" name="act" value="reject">
                                <button type="submit" class="tqa-btn tqa-btn--ghost tqa-btn--sm"><?php echo t('رفض'); ?></button>
                            </form>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
</div>

<div class="tqa-note" style="margin-block-start:var(--tq-space-l)">
    <span aria-hidden="true"><?php echo tq_icon('shield', 18); ?></span>
    <span>
        <?php echo t('الاعتماد يفتح الحساب ويجعله معلما، ويصله بريد بذلك إن كان البريد الصادر مضبوطا. والرفض يبقيه مغلقا ويسجل بمن قرره ومتى. وكلاهما يظهر في سجل التدقيق.'); ?>
    </span>
</div>
