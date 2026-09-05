<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * TQ-SUB-DETAIL — ملف بيعة واحدة.
 *
 * ═══ ما الذي كان ناقصا ═══
 *
 * قائمة الاشتراكات تجيب سؤال المسح: «من اشترك، وبكم، وما حاله؟». وهي
 * تصلح لذلك ولا تصلح لغيره — والذي يفتح اللوحة أكثر ما يفتحها لسؤال
 * **الحادثة**: «هذا الصف بعينه، ماذا جرى فيه؟». يسأله حين يتصل مشتر
 * يقول «دفعت ولم يفتح»، ومعلم يقول «باعوا صفي ولم يصلني شيء»، ومحاسب
 * يقول «هذه الفاتورة لم تسدد».
 *
 * وجواب الثلاثة كان **مبعثرا في خمس شاشات لا يربط بينها رقم**:
 * الفاتورة في «الفواتير»، ومحاولة البطاقة في «الدفع بالبطاقة»،
 * والقسمة في قائمة الصف، والقيد في «قيود المحافظ»، والأثر في سجل
 * التدقيق. فيبحث المسؤول بالاسم في كل واحدة ويجمع الجواب بيده، أو
 * يجيب بالحدس.
 *
 * ═══ وترتيب الشاشة ترتيب السؤال لا ترتيب الجداول ═══
 *
 *   ١ — **ما بيع ولمن وبكم**، وحاله الآن. هذا ما يقرأ أولا.
 *   ٢ — **ما الذي يفتحه فعلا** (`subscription_items`) — وهو غير ما بيع:
 *       النطاق ينسخ بنودا وقت التفعيل، فباقة عدلت أمس لا توسع ما دفع
 *       عنه ولا تضيقه. ومعلق بلا بنود يقرأ «لم يفعل بعد فلا يفتح شيئا»
 *       صراحة، وهو أول جواب لـ«دفعت ولم يفتح».
 *   ٣ — **المال**: فاتورته، ومحاولات بطاقته بحالها من تاب.
 *   ٤ — **من استحق منه**: القسمة، ثم القيد في الدفاتر. وهما سؤالان لا
 *       واحد — صف قسمة بلا قيد يعني أن القيد فشل، وهو غير ألا تكون
 *       قسمت أصلا.
 *   ٥ — **الأثر**: من فعل ماذا ومتى.
 *
 * والإجراءات في الرأس لا في الذيل: من فتح الشاشة ليفعل حوالة لا يمرر
 * خمسة أقسام ليجد الزر.
 */

$sub  = $d['sub'];
$sold = $d['sold'];
$usr  = $d['user'];
$sid  = (int) $sub['id'];
$st   = (string) $sub['status'];

$tq_labels = array('pending' => t('معلق'), 'active' => t('نشط'),
                   'cancelled' => t('ملغى'), 'expired' => t('منته'));
$tq_tone   = array('pending' => 'warn', 'active' => 'ok',
                   'cancelled' => 'danger', 'expired' => 'muted');

/* رابط ما بيع — ليقرأ المسؤول ما اشتراه صاحبه لا اسمه وحده. */
$tq_href = '';
switch ($sold['kind']) {
    case 'book':   $tq_href = site_url('taqdar_admin/form/books/' . (int) $sold['id']); break;
    case 'course': $tq_href = site_url('admin/course_form/course_edit/' . (int) $sold['id']); break;
    case 'path':   $tq_href = site_url('taqdar_admin/form/paths/' . (int) $sold['id']); break;
    case 'plan':   $tq_href = site_url('taqdar_admin/form/plans/' . (int) $sold['id']); break;
}
if ((int) $sold['id'] <= 0) $tq_href = '';

/* المحصل من فواتير هذه البيعة وحدها — لا من `subscriptions.price`:
   السعر ما اتفق عليه، والمسدد ما وصل، وهما يفترقان في كل صف معلق. */
$tq_paid = 0;
foreach ($d['invoices'] as $tq_i) {
    if ((string) $tq_i['status'] === 'paid') $tq_paid += (int) $tq_i['total'];
}
/* ونصيب المعلم **مجموع قيوده كلها** لا سطر «بيعة» وحده: الدفتر يقيد
   المقبوض (`sale`) ثم يخصم منه عمولة المنصة (`commission`) وما يحتجز
   (`retained`)، ومجموع الثلاثة هو حصته بحكم البناء. وقراءة `sale` وحده
   تطبع مئة وخمسين على معلم نصيبه تسعون — رقم يقرؤه المسؤول ويعد به. */
$tq_credited = 0;
foreach ($d['entries'] as $tq_e) $tq_credited += (int) $tq_e['amount'];
?>

<?php
/* الرجوع إلى القائمة في الترويسة: الشاشة تفتح من صف فيها، ومن أنهى
   قراءته يعود إليه لا إلى أول اللوحة. */
tqa_head(
    t('الاشتراك') . ' #' . $sid,
    t('ماذا بيع فيه، وماذا يفتح، وأين ذهب ماله.'),
    'receipt',
    '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('taqdar_admin/subscriptions') . '">'
        . tq_icon('chev-prev', 15) . ' ' . te('كل الاشتراكات') . '</a>'
);
?>

<div class="tqa-stack tqa-stack--stats">
    <?php echo tqa_stat(t('المتفق عليه'), tqa_money($sub['price']),
        array('icon' => 'receipt', 'tone' => 'info',
              'hint' => t('سعر جمد وقت الشراء'))); ?>

    <?php /* المسدد بجوار المتفق: الفرق بينهما هو سؤال «هل دفع؟» كله. */ ?>
    <?php echo tqa_stat(t('المسدد فعلا'), tqa_money($tq_paid),
        array('icon' => 'wallet',
              'tone' => $tq_paid >= (int) $sub['price'] ? 'ok' : ($tq_paid > 0 ? 'warn' : 'danger'),
              'hint' => $tq_paid >= (int) $sub['price'] && (int) $sub['price'] > 0
                    ? t('وصل كاملا')
                    : ($tq_paid > 0 ? t('وصل بعضه') : t('لم يصل شيء')))); ?>

    <?php
    /* والسطر يقرأ من القيود لا من القسمة: الباقة تقسم فتكتب صفوف
       `revenue_shares`، والشراء المفرد لا يقسم شيئا ويقيد نصيبا — فسطر
       مبني على القسمة وحدها يقول «لم يقسم لأحد» فوق رقم غير صفر. */
    $tq_teachers = array();
    foreach ($d['entries'] as $tq_e) $tq_teachers[(int) $tq_e['teacher_id']] = 1;
    $tq_tn = count(array_filter(array_keys($tq_teachers)));
    echo tqa_stat(t('نصيب المعلمين'), tqa_money($tq_credited),
        array('icon' => 'users',
              'tone' => $tq_credited > 0 ? 'ok' : 'info',
              'hint' => $tq_tn > 0
                    ? t('قيد لـ____ معلما', $tq_tn)
                    : t('لم يقيد لأحد'))); ?>

    <?php echo tqa_stat(t('ما يفتحه'), (int) count($d['items']),
        array('icon' => 'package',
              'tone' => $d['items'] ? 'ok' : ($st === 'pending' ? 'warn' : 'danger'),
              'hint' => $d['items'] ? t('بندا في النطاق') : t('لا بند — لا يفتح شيئا'))); ?>
</div>


<?php /* ═══ ١ — ما بيع ولمن ═══════════════════════════════════════ */ ?>
<div class="tqa-card tqa-section">
    <div class="tqa-card__head">
        <span class="tqa-iconbox tqa-sky" aria-hidden="true"><?php echo tq_icon('package', 20); ?></span>
        <div style="min-inline-size:0">
            <h2><?php echo t('البيعة'); ?></h2>
            <span class="tqa-media__sub"><?php echo t('ما بيع، ولمن، وبأي وسيلة.'); ?></span>
        </div>
        <span class="tqa-badge tqa-badge--dot tqa-badge--<?php echo $tq_tone[$st] ?? 'muted'; ?>">
            <?php echo html_escape($tq_labels[$st] ?? $st); ?>
        </span>
    </div>

    <dl class="tqa-fieldgrid" style="margin:0">
        <div>
            <dt class="tqa-field__label"><?php echo t('ما اشترى'); ?></dt>
            <dd style="margin:0">
                <?php if ($tq_href !== ''): ?>
                    <a href="<?php echo html_escape($tq_href); ?>"><?php echo html_escape($sold['title']); ?></a>
                <?php else: ?>
                    <?php echo html_escape($sold['title']); ?>
                <?php endif; ?>
                <span class="tqa-badge tqa-badge--muted"><?php echo html_escape($sold['label']); ?></span>
            </dd>
        </div>
        <div>
            <dt class="tqa-field__label"><?php echo t('المشترك'); ?></dt>
            <dd style="margin:0">
                <?php if ($usr): ?>
                    <a href="<?php echo site_url('taqdar_admin/people?q=' . rawurlencode((string) $usr['email'])); ?>">
                        <?php echo html_escape(trim((string) $usr['name']) ?: ('#' . (int) $usr['id'])); ?>
                    </a>
                    <br><span class="tqa-num tqa-dim" dir="ltr"><?php echo html_escape($usr['email']); ?></span>
                <?php else: ?>
                    <?php /* حساب حذف: الرقم يبقى، وبه يقابل السجل المالي. */ ?>
                    <span class="tqa-dim">#<?php echo (int) $sub['user_id']; ?> — <?php echo t('حساب محذوف'); ?></span>
                <?php endif; ?>
            </dd>
        </div>
        <div>
            <dt class="tqa-field__label"><?php echo t('وسيلة الدفع'); ?></dt>
            <dd class="tqa-num" style="margin:0" dir="ltr">
                <?php echo html_escape(trim((string) $sub['method']) ?: '—'); ?>
            </dd>
        </div>
        <div>
            <dt class="tqa-field__label"><?php echo t('دورة الاشتراك'); ?></dt>
            <dd style="margin:0">
                <?php
                /* TQ-CYCLE-BUY — الدورة معامل شراء لا صف باقة ثان، فتقرأ
                   من الصف نفسه. والقديم بلا عمود يشتق من مدته. */
                $tq_cl = array('annual' => t('سنوي'), 'quarterly' => t('ربع سنوي'),
                               'monthly' => t('شهري'), 'free' => t('مجانية'));
                $tq_ck = (string) ($sub['cycle'] ?? '');
                $tq_dy = (int) ($sub['days'] ?? 0);
                if ($tq_ck === '' && $tq_dy > 0) {
                    $tq_ck = ($tq_dy >= 300) ? 'annual' : (($tq_dy >= 80) ? 'quarterly' : 'monthly');
                }
                echo $tq_ck !== '' ? html_escape($tq_cl[$tq_ck] ?? $tq_ck) : '—';
                if ($tq_dy > 0) echo ' <span class="tqa-dim">· ' . t('____ يوما', $tq_dy) . '</span>';
                ?>
            </dd>
        </div>
        <div>
            <dt class="tqa-field__label"><?php echo t('تاريخ البدء'); ?></dt>
            <dd class="tqa-num" style="margin:0" dir="ltr"><?php
                echo $sub['started_at'] ? html_escape(date('Y-m-d H:i', strtotime($sub['started_at']))) : '—';
            ?></dd>
        </div>
        <div>
            <dt class="tqa-field__label"><?php echo t('تاريخ الانتهاء'); ?></dt>
            <dd class="tqa-num" style="margin:0" dir="ltr"><?php
                /* الفارغ على صف نشط يعني **دائما** لا مجهولا — TQ-COURSE-SALE:
                   أجل صفر لا `ends_at` له، و«—» وحدها تقرأ نقصا في البيانات. */
                if (!empty($sub['ends_at'])) {
                    echo html_escape(date('Y-m-d H:i', strtotime($sub['ends_at'])));
                } elseif ($st === 'active') {
                    echo '<span class="tqa-badge tqa-badge--ok">' . te('وصول دائم') . '</span>';
                } else { echo '—'; }
            ?></dd>
        </div>
        <div>
            <dt class="tqa-field__label"><?php echo t('تاريخ الإنشاء'); ?></dt>
            <dd class="tqa-num" style="margin:0" dir="ltr"><?php
                echo !empty($sub['created_at']) ? html_escape(date('Y-m-d H:i', strtotime($sub['created_at']))) : '—';
            ?></dd>
        </div>
        <div>
            <dt class="tqa-field__label"><?php echo t('مرجع العملية'); ?></dt>
            <dd class="tqa-num" style="margin:0" dir="ltr">
                <?php echo html_escape(trim((string) $sub['transaction_id']) ?: '—'); ?>
            </dd>
        </div>
    </dl>

    <?php if ($st === 'cancelled' && trim((string) $sub['cancel_reason']) !== ''): ?>
        <?php /* السبب يعرض مترجما لا مخزنا: TQ-I18N — الرسالة تولد في
                 نموذج وتخزن، ولفها عند الكتابة يخزنها بلغة من كتبها. */ ?>
        <p class="tqa-note" style="margin-block-start:var(--tq-space-l)">
            <strong><?php echo t('سبب الإلغاء:'); ?></strong>
            <?php echo html_escape(t((string) $sub['cancel_reason'])); ?>
            <?php if (!empty($sub['cancelled_at'])): ?>
                <span class="tqa-dim tqa-num" dir="ltr">· <?php echo date('Y-m-d H:i', strtotime($sub['cancelled_at'])); ?></span>
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <?php /* ── الإجراءات ─────────────────────────────────────────────
             وهي إجراءات القائمة نفسها بمساراتها نفسها — لا نسخة ثانية
             منها: `back` وحده يزيد، فيعود الرد إلى هذه الشاشة بدل
             القائمة. وشاشتان تفعلان بمسارين تفترقان عند أول تعديل. */ ?>
    <div class="tqa-actions" style="margin-block-start:var(--tq-space-l);gap:var(--tq-space-m);flex-wrap:wrap">
        <?php if ($st === 'pending'): ?>
            <form method="post" style="display:flex;gap:var(--tq-space-s);flex-wrap:wrap;align-items:center;margin:0"
                  action="<?php echo site_url('taqdar_admin/subscription_activate/' . $sid); ?>"
                  data-tqa-confirm-title="<?php echo te('تفعيل الاشتراك'); ?>"
                  data-tqa-confirm="<?php echo te('سيسدد الاشتراك ويفتح محتواه للطالب فورا. تأكد من وصول الحوالة أولا.'); ?>"
                  data-tqa-confirm-ok="<?php echo te('فعل الاشتراك'); ?>">
                <?php echo tq_csrf(); ?>
                <input type="hidden" name="back" value="<?php echo $sid; ?>">
                <input class="tqa-input tq-ltr" type="text" name="reference" dir="ltr" required
                       placeholder="<?php echo te('مرجع الحوالة'); ?>"
                       aria-label="<?php echo te('مرجع الحوالة'); ?>">
                <button type="submit" class="tqa-btn tqa-btn--primary">
                    <?php echo tq_icon('bank', 16); ?> <?php echo t('فعل بتحويل بنكي'); ?>
                </button>
            </form>
        <?php elseif ($st === 'active'): ?>
            <form method="post" style="margin:0"
                  action="<?php echo site_url('taqdar_admin/subscription_cancel/' . $sid); ?>"
                  data-tqa-confirm-title="<?php echo te('إلغاء التجديد'); ?>"
                  data-tqa-confirm="<?php echo te('يبقى الاشتراك صالحا حتى تاريخ انتهائه، ولا يجدد بعده.'); ?>"
                  data-tqa-confirm-ok="<?php echo te('ألغ التجديد'); ?>"
                  data-tqa-confirm-tone="danger">
                <?php echo tq_csrf(); ?>
                <input type="hidden" name="back" value="<?php echo $sid; ?>">
                <button type="submit" class="tqa-btn tqa-btn--danger">
                    <?php echo tq_icon('close', 16); ?> <?php echo t('ألغ التجديد'); ?>
                </button>
            </form>
        <?php endif; ?>

        <?php if ($usr): ?>
            <a class="tqa-btn tqa-btn--ghost"
               href="<?php echo site_url('taqdar_admin/module/invoices?q=' . rawurlencode((string) $usr['email'])); ?>">
                <?php echo tq_icon('receipt', 16); ?> <?php echo t('فواتير هذا المشترك'); ?>
            </a>
        <?php endif; ?>
    </div>
</div>


<?php /* ═══ ٢ — ما يفتحه فعلا ═══════════════════════════════════════
         وهو **غير ما بيع**: البنود صورة النطاق وقت التفعيل، فباقة
         تعدل غدا لا توسع ما دفع عنه ولا تضيقه. ومن يسأل «لم لا يفتح
         له الكورس؟» يجاب من هنا لا من صفحة الباقة. */ ?>
<div class="tqa-card tqa-card--flush tqa-section">
    <div class="tqa-card__head">
        <span class="tqa-iconbox tqa-mint" aria-hidden="true"><?php echo tq_icon('key', 20); ?></span>
        <div style="min-inline-size:0">
            <h2><?php echo t('ما يفتحه هذا الاشتراك'); ?></h2>
            <span class="tqa-media__sub">
                <?php echo t('نطاق نسخ وقت التفعيل — تعديل الباقة بعده لا يوسعه ولا يضيقه.'); ?>
            </span>
        </div>
        <span class="tqa-badge tqa-badge--muted">
            <span class="tqa-num"><?php echo count($d['items']); ?></span>&nbsp;<?php echo t('بندا'); ?>
        </span>
    </div>
    <div>
    <?php if (!$d['items']): ?>
        <?php
        /* والفراغ يقال بسببه: بند ناقص على صف نشط عطل، وعلى صف معلق
           هو الحال الطبيعية — وسطر واحد لهما يترك المسؤول يحدس. */
        tqa_empty(
            t('لا بنود — لا يفتح شيئا'),
            $st === 'pending'
                ? t('الاشتراك لم يفعل بعد، والبنود تكتب وقت التفعيل. فعله من الأعلى وستظهر هنا.')
                : t('صف نشط بلا بنود لا يفتح محتوى لصاحبه. راجع سجل التدقيق أدناه، وأعد التفعيل إن لزم.'),
            '', '', 'lock');
        ?>
    <?php else: ?>
        <div class="tqa-table__wrap">
            <table class="tqa-table tqa-table--zebra">
                <thead>
                    <tr>
                        <th><?php echo t('النوع'); ?></th>
                        <th><?php echo t('العنصر'); ?></th>
                        <th class="tqa-col--tight"><?php echo t('المعرف'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $tq_kinds = array('all' => t('كل المحتوى'), 'grade' => t('صف'),
                                  'subject' => t('مادة'),   'path'  => t('مسار'),
                                  'course' => t('كورس'),    'book'  => t('كتاب'),
                                  'trial' => t('تجربة'));
                foreach ($d['items'] as $it): ?>
                    <tr>
                        <td data-label="<?php echo te('النوع'); ?>">
                            <span class="tqa-badge tqa-badge--info">
                                <?php echo html_escape($tq_kinds[$it['entity_type']] ?? $it['entity_type']); ?>
                            </span>
                        </td>
                        <td data-label="<?php echo te('العنصر'); ?>"><?php echo html_escape($it['name']); ?></td>
                        <td class="tqa-num" data-label="<?php echo te('المعرف'); ?>">
                            <?php echo (int) $it['entity_id'] > 0 ? '#' . (int) $it['entity_id'] : '—'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    </div>
</div>


<?php /* ═══ ٣ — الفواتير ════════════════════════════════════════════ */ ?>
<div class="tqa-card tqa-card--flush tqa-section">
    <div class="tqa-card__head">
        <span class="tqa-iconbox tqa-peach" aria-hidden="true"><?php echo tq_icon('receipt', 20); ?></span>
        <div style="min-inline-size:0">
            <h2><?php echo t('الفواتير'); ?></h2>
            <span class="tqa-media__sub">
                <?php echo t('الفاتورة تصدر قبل الدفع دائما — فمن دفع وانقطع اتصاله له عندنا ما يقابل دفعته.'); ?>
            </span>
        </div>
    </div>
    <div>
    <?php if (!$d['invoices']): ?>
        <?php tqa_empty(t('لا فاتورة لهذا الاشتراك'),
              t('كل شراء يصدر فاتورته أولا. صف بلا فاتورة كتب من خارج مسار الشراء — منحة من اللوحة، أو باقة مجانية.'),
              '', '', 'receipt'); ?>
    <?php else: ?>
        <div class="tqa-table__wrap">
            <table class="tqa-table tqa-table--zebra">
                <thead>
                    <tr>
                        <th><?php echo t('الرقم'); ?></th>
                        <th><?php echo t('المبلغ'); ?></th>
                        <th><?php echo t('الحال'); ?></th>
                        <th><?php echo t('الوسيلة'); ?></th>
                        <th><?php echo t('صدرت'); ?></th>
                        <th><?php echo t('سددت'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $tq_ist = array('paid' => array(t('مدفوعة'), 'ok'),
                                'unpaid' => array(t('غير مدفوعة'), 'danger'),
                                'refunded' => array(t('مستردة'), 'muted'));
                foreach ($d['invoices'] as $inv):
                    $tq_s = $tq_ist[$inv['status']] ?? array($inv['status'], 'muted');
                ?>
                    <tr>
                        <td class="tqa-num" data-label="<?php echo te('الرقم'); ?>" dir="ltr">
                            <?php echo html_escape($inv['invoice_no']); ?>
                        </td>
                        <td data-label="<?php echo te('المبلغ'); ?>">
                            <div class="tqa-cell">
                                <span class="tqa-cell__main"><?php echo tqa_money($inv['total']); ?></span>
                                <?php if ((int) $inv['tax'] > 0): ?>
                                    <?php /* الضريبة تقال منفصلة: القسمة على `amount`
                                             لا على `total` — الضريبة مال الدولة. */ ?>
                                    <span class="tqa-cell__sub tqa-dim">
                                        <?php echo t('منها ضريبة ____', tqa_money($inv['tax'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td data-label="<?php echo te('الحال'); ?>">
                            <span class="tqa-badge tqa-badge--dot tqa-badge--<?php echo $tq_s[1]; ?>">
                                <?php echo html_escape($tq_s[0]); ?>
                            </span>
                        </td>
                        <td class="tqa-num" data-label="<?php echo te('الوسيلة'); ?>" dir="ltr">
                            <?php echo html_escape(trim((string) $inv['method']) ?: '—'); ?>
                        </td>
                        <td class="tqa-num" data-label="<?php echo te('صدرت'); ?>" dir="ltr"><?php
                            echo !empty($inv['issued_at']) ? html_escape(date('Y-m-d H:i', strtotime($inv['issued_at']))) : '—';
                        ?></td>
                        <td class="tqa-num" data-label="<?php echo te('سددت'); ?>" dir="ltr"><?php
                            echo !empty($inv['paid_at']) ? html_escape(date('Y-m-d H:i', strtotime($inv['paid_at']))) : '—';
                        ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    </div>
</div>


<?php /* ═══ ٣ب — محاولات البطاقة ════════════════════════════════════
         لا تعرض إلا حين توجد: صف دفع بحوالة بنكية لا محاولات له،
         وقسم فارغ في كل شاشة يعلم القارئ أن يتخطاه فيتخطاه حين يهم. */ ?>
<?php if ($d['attempts']): ?>
<div class="tqa-card tqa-card--flush tqa-section">
    <div class="tqa-card__head">
        <span class="tqa-iconbox tqa-sky" aria-hidden="true"><?php echo tq_icon('card', 20); ?></span>
        <div style="min-inline-size:0">
            <h2><?php echo t('محاولات الدفع بالبطاقة'); ?></h2>
            <span class="tqa-media__sub">
                <?php echo t('لكل محاولة صف بقيمة الفاتورة وقت البدء — وما ترده تاب يقابله قبل أن يفعل شيء.'); ?>
            </span>
        </div>
        <a class="tqa-btn tqa-btn--ghost tqa-btn--sm" href="<?php echo site_url('taqdar_admin/tap'); ?>">
            <?php echo t('شاشة بوابة تاب'); ?>
        </a>
    </div>
    <div>
        <div class="tqa-table__wrap">
            <table class="tqa-table tqa-table--zebra">
                <thead>
                    <tr>
                        <th><?php echo t('معرف الدفعة'); ?></th>
                        <th><?php echo t('المبلغ'); ?></th>
                        <th><?php echo t('حالنا'); ?></th>
                        <th><?php echo t('حال البوابة'); ?></th>
                        <th><?php echo t('متى'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                /* «حالنا» و«حال البوابة» عمودان لا واحد: `mismatch` عندنا
                   على دفعة `CAPTURED` عندهم هو بعينه ما يفتش عنه من يسأل
                   «دفعت ولم يفتح» — TQ-TAP قاعدة «المبلغ يقابل الفاتورة». */
                $tq_at = array('paid' => 'ok', 'captured' => 'ok', 'initiated' => 'warn',
                               'pending' => 'warn', 'failed' => 'danger',
                               'mismatch' => 'danger', 'declined' => 'danger');
                foreach ($d['attempts'] as $a):
                    $tq_k = strtolower((string) $a['status']);
                ?>
                    <tr>
                        <td class="tqa-num" data-label="<?php echo te('معرف الدفعة'); ?>" dir="ltr">
                            <?php echo html_escape($a['charge_id'] ?: '—'); ?>
                            <?php if (trim((string) $a['mode']) !== ''): ?>
                                <span class="tqa-badge tqa-badge--muted"><?php echo html_escape($a['mode']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="tqa-num" data-label="<?php echo te('المبلغ'); ?>">
                            <?php echo tqa_money($a['amount']); ?>
                        </td>
                        <td data-label="<?php echo te('حالنا'); ?>">
                            <span class="tqa-badge tqa-badge--<?php echo $tq_at[$tq_k] ?? 'muted'; ?>">
                                <?php echo html_escape($a['status']); ?>
                            </span>
                        </td>
                        <td data-label="<?php echo te('حال البوابة'); ?>">
                            <div class="tqa-cell">
                                <span class="tqa-cell__main tqa-num" dir="ltr">
                                    <?php echo html_escape($a['gateway_status'] ?: '—'); ?>
                                </span>
                                <?php if (trim((string) $a['message']) !== ''): ?>
                                    <span class="tqa-cell__sub tqa-dim"><?php echo html_escape($a['message']); ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="tqa-num" data-label="<?php echo te('متى'); ?>" dir="ltr"><?php
                            $tq_w = $a['updated_at'] ?: $a['created_at'];
                            echo $tq_w ? html_escape(date('Y-m-d H:i', strtotime($tq_w))) : '—';
                        ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>


<?php /* ═══ ٤ — القسمة والقيد ═══════════════════════════════════════
         سؤالان لا واحد: **أقسمت؟** (`revenue_shares`) و**أوصل؟**
         (`wallet_entries`). وصف قسمة بلا قيد يعني أن القيد فشل — وهو
         غير ألا تكون قسمت أصلا، ويعالج بغير ما تعالج به. */ ?>
<div class="tqa-card tqa-card--flush tqa-section">
    <div class="tqa-card__head">
        <span class="tqa-iconbox tqa-mint" aria-hidden="true"><?php echo tq_icon('wallet', 20); ?></span>
        <div style="min-inline-size:0">
            <h2><?php echo t('قسمة الإيراد'); ?></h2>
            <span class="tqa-media__sub">
                <?php echo t('تجمد وقت التفعيل — فنشر عشرين درسا غدا لا يعيد حساب بيعة أمس.'); ?>
            </span>
        </div>
    </div>
    <div>
    <?php if (!$d['shares']): ?>
        <?php
        /* والفراغ يقال بسببه، وسببه يختلف باختلاف ما بيع:
           الباقة تقسم **وعاء** على معلمين كثر، فخلوه يعني ألا مسار منشور
           بمعلم في نطاقها. والشراء المفرد — كورسا أو كتابا — **لا وعاء
           له أصلا**: نسبة واحدة لصاحب واحد تقيد في دفتره مباشرة، وهي في
           «ما قيد في الدفاتر» أدناه لا هنا. وجملة واحدة للحالين تجعل
           المسؤول يبحث عن مسار في بيعة كتاب. */
        if ((int) $sub['price'] <= 0) {
            tqa_empty(t('لم تقسم على أحد'), t('بيعة بلا ثمن لا تقسم.'), '', '', 'wallet');
        } elseif ($sold['kind'] !== 'plan') {
            tqa_empty(t('لا وعاء يقسم في شراء مفرد'),
                t('الباقة وحدها تقسم وعاء على معلمين كثر لأنها تفتح محتواهم جميعا. والشراء المفرد لصاحب واحد: نسبة واحدة تقيد في دفتره، وتقرأ في «ما قيد في الدفاتر» أدناه.'),
                '', '', 'wallet');
        } else {
            tqa_empty(t('لم تقسم على أحد'),
                t('لا مسار منشور بمعلم في نطاق ما بيع وقت البيع — فبقي المال كله للمنصة.'),
                '', '', 'wallet');
        }
        ?>
    <?php else: ?>
        <div class="tqa-table__wrap">
            <table class="tqa-table tqa-table--zebra">
                <thead>
                    <tr>
                        <th><?php echo t('المعلم'); ?></th>
                        <th><?php echo t('نصيبه'); ?></th>
                        <th><?php echo t('وزنه'); ?></th>
                        <th><?php echo t('الوعاء'); ?></th>
                        <th><?php echo t('قسم'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($d['shares'] as $s): ?>
                    <tr>
                        <td data-label="<?php echo te('المعلم'); ?>">
                            <a href="<?php echo site_url('taqdar_admin/teacher/' . (int) $s['teacher_id']); ?>">
                                <?php echo html_escape(trim((string) $s['teacher_name']) ?: ('#' . (int) $s['teacher_id'])); ?>
                            </a>
                        </td>
                        <td class="tqa-num" data-label="<?php echo te('نصيبه'); ?>">
                            <?php echo tqa_money($s['amount_halalas']); ?>
                        </td>
                        <td data-label="<?php echo te('وزنه'); ?>" class="tqa-num">
                            <?php echo t('____ من ____', array((int) $s['lessons'], (int) $s['lessons_total'])); ?>
                        </td>
                        <td class="tqa-num" data-label="<?php echo te('الوعاء'); ?>">
                            <?php echo tqa_money($s['pool_halalas']); ?>
                            <?php /* النسبة بين قوسين مفتاح واحد ببديله: علامة
                                     المئة خارج `t()` نص ظاهر لا يترجم. */ ?>
                            <span class="tqa-dim"><?php echo html_escape(
                                t('(____٪ من السعر)', (float) $s['pool_percent'])); ?></span>
                        </td>
                        <td class="tqa-num" data-label="<?php echo te('قسم'); ?>" dir="ltr"><?php
                            echo !empty($s['created_at']) ? html_escape(date('Y-m-d H:i', strtotime($s['created_at']))) : '—';
                        ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php /* TQ-REVENUE-RESPLIT — والمخرج بقرار مسؤول لا مسار تلقائي:
             نقل مال بعد أن قيد ليس تصحيح رقم.

             **والباقة وحدها**: `resplit_plan_sale()` تعكس أصول
             `plansub:` وتنادي `credit_plan_sale()`، وهي تقرأ باقة —
             وصف الكورس أو الكتاب المفرد `plan_id = 0`. فالزر عليه يعكس
             لا شيء ثم يفشل برسالة لا تفهم، ويترك قيده كما هو. والقسمة
             هناك نسبة واحدة لصاحب واحد أصلا: لا وعاء يعاد قسمه. */ ?>
    <?php if ($sold['kind'] === 'plan' && (int) $sub['price'] > 0
              && in_array($st, array('active', 'cancelled'), true)): ?>
        <div style="padding:var(--tq-space-l)">
            <form method="post" style="display:flex;gap:var(--tq-space-s);flex-wrap:wrap;align-items:center;margin:0"
                  action="<?php echo site_url('taqdar_admin/subscription_resplit/' . $sid); ?>"
                  data-tqa-confirm-title="<?php echo te('إعادة قسمة الإيراد'); ?>"
                  data-tqa-confirm="<?php echo te('تعكس القيود القائمة على هذه البيعة وتقسمها من جديد على المستحقين الآن. ينقل مال بين المحافظ، ويسجل في سجل التدقيق.'); ?>"
                  data-tqa-confirm-ok="<?php echo te('أعد القسمة'); ?>"
                  data-tqa-confirm-tone="danger">
                <?php echo tq_csrf(); ?>
                <input type="hidden" name="back" value="<?php echo $sid; ?>">
                <input class="tqa-input" type="text" name="reason" required maxlength="200"
                       placeholder="<?php echo te('سبب إعادة القسمة'); ?>"
                       aria-label="<?php echo te('سبب إعادة القسمة'); ?>">
                <button type="submit" class="tqa-btn tqa-btn--danger">
                    <?php echo tq_icon('refresh', 16); ?> <?php echo t('أعد القسمة'); ?>
                </button>
            </form>
            <p class="tqa-note" style="margin-block-start:var(--tq-space-s)">
                <?php echo t('تلزم حين يحذف المحتوى المقسوم عليه ثم ينشر غيره في النطاق نفسه: القيد قائم لمن لا محتوى له، ومن يخدم المشتركين اليوم محفظته صفر.'); ?>
            </p>
        </div>
    <?php endif; ?>
    </div>
</div>


<?php /* ═══ ٤ب — القيد في الدفاتر ═══════════════════════════════════ */ ?>
<?php if ($d['entries']): ?>
<div class="tqa-card tqa-card--flush tqa-section">
    <div class="tqa-card__head">
        <span class="tqa-iconbox tqa-mint" aria-hidden="true"><?php echo tq_icon('clipboard', 20); ?></span>
        <div style="min-inline-size:0">
            <h2><?php echo t('ما قيد في الدفاتر'); ?></h2>
            <span class="tqa-media__sub">
                <?php echo t('القسمة رقم يحسب، والقيد مال يدخل دفترا — وهما سؤالان لا واحد.'); ?>
            </span>
        </div>
    </div>
    <div>
        <div class="tqa-table__wrap">
            <table class="tqa-table tqa-table--zebra">
                <thead>
                    <tr>
                        <th><?php echo t('المعلم'); ?></th>
                        <th><?php echo t('النوع'); ?></th>
                        <th><?php echo t('المبلغ'); ?></th>
                        <th><?php echo t('الوعاء'); ?></th>
                        <th><?php echo t('متى'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $tq_et = array('sale' => t('بيعة'), 'commission' => t('عمولة المنصة'),
                               'retained' => t('محتجز'), 'reverse' => t('عكس قيد'),
                               'release_in' => t('تحرير — دخول'), 'release_out' => t('تحرير — خروج'));
                $tq_bk = array('pending' => t('معلق'), 'available' => t('متاح'),
                               'paid' => t('مصروف'), 'reversed' => t('معكوس'));
                foreach ($d['entries'] as $e): ?>
                    <tr>
                        <td data-label="<?php echo te('المعلم'); ?>">
                            <?php echo html_escape(trim((string) $e['teacher_name']) ?: ('#' . (int) $e['teacher_id'])); ?>
                        </td>
                        <td data-label="<?php echo te('النوع'); ?>">
                            <?php echo html_escape($tq_et[$e['type']] ?? $e['type']); ?>
                        </td>
                        <td class="tqa-num" data-label="<?php echo te('المبلغ'); ?>">
                            <?php echo tqa_money($e['amount']); ?>
                        </td>
                        <td data-label="<?php echo te('الوعاء'); ?>">
                            <span class="tqa-badge tqa-badge--muted">
                                <?php echo html_escape($tq_bk[$e['bucket']] ?? $e['bucket']); ?>
                            </span>
                        </td>
                        <td class="tqa-num" data-label="<?php echo te('متى'); ?>" dir="ltr"><?php
                            echo !empty($e['occurred_at']) ? html_escape(date('Y-m-d H:i', strtotime($e['occurred_at']))) : '—';
                        ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>


<?php /* ═══ ٥ — الأثر ═══════════════════════════════════════════════ */ ?>
<?php if ($d['audit']): ?>
<div class="tqa-card tqa-card--flush tqa-section">
    <div class="tqa-card__head">
        <span class="tqa-iconbox tqa-peach" aria-hidden="true"><?php echo tq_icon('clock', 20); ?></span>
        <div style="min-inline-size:0">
            <h2><?php echo t('سجل التدقيق'); ?></h2>
            <span class="tqa-media__sub"><?php echo t('من فعل ماذا بهذا الاشتراك، ومتى.'); ?></span>
        </div>
    </div>
    <div>
        <div class="tqa-table__wrap">
            <table class="tqa-table tqa-table--zebra">
                <thead>
                    <tr>
                        <th><?php echo t('الإجراء'); ?></th>
                        <th><?php echo t('من نفذه'); ?></th>
                        <th><?php echo t('متى'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($d['audit'] as $a): ?>
                    <tr>
                        <td class="tqa-num" data-label="<?php echo te('الإجراء'); ?>" dir="ltr">
                            <?php echo html_escape($a['action']); ?>
                        </td>
                        <td data-label="<?php echo te('من نفذه'); ?>">
                            <?php echo html_escape(trim((string) $a['actor_name']) ?: ('#' . (int) $a['actor_id'])); ?>
                        </td>
                        <td class="tqa-num" data-label="<?php echo te('متى'); ?>" dir="ltr"><?php
                            echo !empty($a['at']) ? html_escape(date('Y-m-d H:i', strtotime($a['at']))) : '—';
                        ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
