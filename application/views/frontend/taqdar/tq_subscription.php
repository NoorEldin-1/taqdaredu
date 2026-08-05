<?php
defined('BASEPATH') or exit('No direct script access allowed');

$labels = array('pending' => 'بانتظار السداد', 'active' => 'نشط', 'cancelled' => 'ملغى التجديد', 'expired' => 'منتهٍ');

/* الحال الفعلية لا المخزَّنة: الكرون يمرّ ليلًا، والطالب يقرأ الآن. */
$eff = $current ? $current['status'] : null;
if ($current && in_array($eff, array('active', 'cancelled'), true)
    && !empty($current['ends_at']) && strtotime($current['ends_at']) < time()) {
    $eff = 'expired';
}

/* نطاق الباقة يُقرأ من الباقة نفسها: «اشتراك» كلمة واحدة تحتها معنيان،
   والمجّانية لا تفتح ما تفتحه المدفوعة. فيُقال للطالب ما اشترك فيه بالضبط
   بدل أن يكتشفه عند أوّل درس مقفل. */
$CI = &get_instance();
$CI->load->model('taqdar_billing_model');
$tq_plan  = $current ? $CI->taqdar_billing_model->plan($current['plan_id']) : null;
$tq_trial = $tq_plan && $tq_plan['scope'] === 'trial';

/* ما تشمله الباقة — من المصدر نفسه الذي تقرأ منه صفحتها العامّة.
   كانت هذه الصفحة تقول «نشط حتى كذا» وتعرض فاتورة، ولا تذكر ما اشتُري:
   يرى الطالب حالةً وتاريخًا ثمّ يبحث عن دروسه في قائمةٍ جانبية. */
$CI->load->model('taqdar_site_model', 'tq_site_m');
$tq_bundle = $tq_plan ? $CI->tq_site_m->bundle_by_code($tq_plan['code']) : null;

/* آخر فاتورةٍ غير مدفوعة: مرجعُ الحوالة رقمُها، وبدونه تصل حوالةٌ
   بلا اسمٍ يُطابَق فيُفتح الاشتراك بالتخمين أو لا يُفتح. */
$tq_due = null;
foreach ((array) $invoices as $tq_i) {
    if ($tq_i['status'] !== 'paid') { $tq_due = $tq_i; break; }
}
?>

<div class="tq-page">
    <header class="tq-page__head">
        <h1 class="tq-h1">اشتراكي</h1>
        <p class="tq-caption">حالة اشتراكك وتاريخ انتهائه وفواتيرك.</p>
    </header>

    <?php if ($flash = $this->session->flashdata('flash_message')): ?>
        <div class="tq-alert tq-alert--ok"><?php echo html_escape($flash); ?></div>
    <?php endif; ?>
    <?php if ($err = $this->session->flashdata('error_message')): ?>
        <div class="tq-alert tq-alert--no"><?php echo html_escape($err); ?></div>
    <?php endif; ?>

    <?php if (!$current): ?>

        <div class="tq-card tq-card--panel">
            <h2 class="tq-card__title">لا اشتراك نشط</h2>
            <p class="tq-caption">
                يمكنك تصفّح الدروس المعلَّمة تجريبية، ويفتح الاشتراك المدفوع بقيّة المحتوى.
            </p>
            <a class="tq-btn tq-btn--primary" href="<?php echo base_url('plans'); ?>">اطّلع على الباقات</a>
        </div>

    <?php else: ?>

        <div class="tq-card tq-card--panel">
            <h2 class="tq-card__title">الباقة <?php echo html_escape($current['plan_name']); ?></h2>

            <dl class="tq-deflist">
                <dt>الحالة</dt>
                <dd><?php echo html_escape($labels[$eff]); ?></dd>

                <dt>القيمة</dt>
                <dd><span class="tq-ltr" dir="ltr"><?php echo number_format(((int) $current['price']) / 100, 2); ?></span> ر.س</dd>

                <?php if ($current['started_at']): ?>
                    <dt>بدأ في</dt>
                    <dd><span class="tq-ltr" dir="ltr"><?php echo date('Y-m-d', strtotime($current['started_at'])); ?></span></dd>
                <?php endif; ?>

                <?php if ($current['ends_at']): ?>
                    <dt>ينتهي في</dt>
                    <dd><span class="tq-ltr" dir="ltr"><?php echo date('Y-m-d', strtotime($current['ends_at'])); ?></span></dd>
                <?php endif; ?>
            </dl>

            <?php if ($tq_trial && in_array($eff, array('active', 'cancelled'), true)): ?>
                <p class="tq-caption">
                    هذه باقة تجريبية: تفتح الدروس المعلَّمة تجريبية وحدها، وبقيّة الدروس
                    تبقى مقفلة حتى تشترك في باقة مدفوعة.
                </p>
            <?php endif; ?>

            <?php if ($eff === 'pending'): ?>
                <p class="tq-caption">
                    صدرت فاتورتك وتنتظر السداد بالتحويل البنكي، ثم يُفعَّل اشتراكك يدويًّا
                    بعد التحقّق من وصول الحوالة.
                </p>
            <?php elseif ($eff === 'active'): ?>
                <p class="tq-caption">
                    اشتراكك لا يُجدَّد تلقائيًّا ولا يُخصم منك شيء بلا طلبك.
                    <?php if ($tq_trial): ?>
                        وللانتقال إلى باقة مدفوعة أوقِف التجربة أوّلًا ثم اختر باقتك.
                    <?php endif; ?>
                </p>
                <?php /* الإلغاء فعل لا يُستردّ، فيكون POST — ورابط GET يُنفَّذ بمجرّد جلبه. */ ?>
                <form method="post" action="<?php echo base_url('student/subscription_cancel'); ?>" class="tq-form-inline">
                    <button type="submit" class="tq-btn tq-btn--secondary tq-btn--sm">
                        <?php echo $tq_trial ? 'إيقاف التجربة' : 'إيقاف التجديد'; ?>
                    </button>
                </form>
            <?php elseif ($eff === 'cancelled'): ?>
                <p class="tq-caption">
                    أوقفتَ التجديد، ويبقى اشتراكك صالحًا حتى تاريخ انتهائه أعلاه.
                    ويمكنك من الآن اختيار باقة أخرى من صفحة الباقات.
                </p>
            <?php elseif ($eff === 'expired'): ?>
                <p class="tq-caption">انتهت مدّة هذا الاشتراك. يمكنك الاشتراك من جديد متى شئت.</p>
                <a class="tq-btn tq-btn--primary tq-btn--sm" href="<?php echo base_url('plans'); ?>">الباقات</a>
            <?php endif; ?>
        </div>

    <?php endif; ?>

    <?php if ($current && $tq_bundle && !empty($tq_bundle['subjects'])): ?>
        <?php $tq_t = $tq_bundle['totals']; ?>
        <section class="tq-section tqb-incl">
            <h2 class="tq-h2">باقتك تشمل</h2>

            <?php
            echo tqs_stat_strip(array(
                array($tq_t['grades'],   'صفوف',     'i-cap'),
                array($tq_t['subjects'], 'مادّة',    'i-book'),
                array($tq_t['units'],    'وحدة',     'i-grid'),
                array($tq_t['lessons'],  'درسًا',    'i-play'),
                array($tq_t['quizzes'],  'اختبارًا', 'i-clipboard'),
            ), 'tqb-stats');
            ?>

            <ul class="tqb-subj">
                <?php foreach ($tq_bundle['subjects'] as $tq_s): ?>
                    <li class="tqb-subj__i<?php echo $tq_s['ready'] ? '' : ' is-soon'; ?>">
                        <b><?php echo html_escape($tq_s['title']); ?></b>
                        <span>
                            <?php if ($tq_s['ready']): ?>
                                <?php echo (int) $tq_s['lessons']; ?> درسًا
                            <?php else: ?>
                                قيد الإعداد
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($eff === 'active' || $eff === 'cancelled'): ?>
                <a class="tq-btn tq-btn--primary" href="<?php echo base_url('student/bundle'); ?>">
                    افتح محتوى الباقة
                </a>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($eff === 'pending' && $tq_due): ?>
        <section class="tq-section">
            <h2 class="tq-h2">كيف تُفعّل اشتراكك</h2>
            <p class="tq-caption">
                حوّل قيمة الفاتورة إلى الحساب أدناه، واكتب رقم الفاتورة في خانة الملاحظات.
            </p>
            <?php echo tqs_bank_block($tq_due['invoice_no'], (int) $tq_due['total']); ?>
        </section>
    <?php endif; ?>

    <section class="tq-section">
        <h2 class="tq-h2">الفواتير</h2>

        <?php if (empty($invoices)): ?>
            <p class="tq-caption">لا فواتير بعد.</p>
        <?php else: ?>
            <div class="tq-table-wrap">
                <table class="tq-table">
                    <thead>
                        <tr>
                            <th>رقم الفاتورة</th>
                            <th>الإجمالي</th>
                            <th>الحالة</th>
                            <th>التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td><span class="tq-ltr" dir="ltr"><?php echo html_escape($inv['invoice_no']); ?></span></td>
                            <td><span class="tq-ltr" dir="ltr"><?php echo number_format(((int) $inv['total']) / 100, 2); ?></span> ر.س</td>
                            <td>
                                <?php echo $inv['status'] === 'paid' ? 'مدفوعة'
                                        : ($inv['status'] === 'refunded' ? 'مستردّة' : 'غير مدفوعة'); ?>
                            </td>
                            <td><span class="tq-ltr" dir="ltr"><?php echo date('Y-m-d', strtotime($inv['issued_at'])); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
