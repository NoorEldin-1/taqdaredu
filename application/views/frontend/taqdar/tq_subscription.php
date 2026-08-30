<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * اشتراكي — حالة الاشتراك وما فتحه وفواتيره.
 *
 * كانت هذه الصفحة تعرض وسمها **خارج غلاف البوابة**: لا قائمة جانبية ولا
 * ترويسة ولا سطح أبيض تحته. فتقرأ كصفحة انهارت لا كصفحة. وسببها سطران
 * غائبان — `portal_open.php` و`portal_close.php` — لا خطأ في منطقها.
 * وكل صفحة بوابة تفتح بهما، وهذه كانت الاستثناء الوحيد.
 */
$labels = array('pending' => t('بانتظار السداد'), 'active' => t('نشط'), 'cancelled' => t('ملغى التجديد'), 'expired' => t('منته'));

/* الحال الفعلية لا المخزنة: الكرون يمر ليلا، والطالب يقرأ الآن. */
$eff = $current ? $current['status'] : null;
if ($current && in_array($eff, array('active', 'cancelled'), true)
    && !empty($current['ends_at']) && strtotime($current['ends_at']) < time()) {
    $eff = 'expired';
}

/* نطاق الباقة يقرأ من الباقة نفسها: «اشتراك» كلمة واحدة تحتها معنيان،
   والمجانية لا تفتح ما تفتحه المدفوعة. فيقال للطالب ما اشترك فيه بالضبط
   بدل أن يكتشفه عند أول درس مقفل. */
$CI = &get_instance();
$CI->load->model('taqdar_billing_model');
$tq_plan  = $current ? $CI->taqdar_billing_model->plan($current['plan_id']) : null;
$tq_trial = $tq_plan && $tq_plan['scope'] === 'trial';

/* ما تشمله الباقة — من المصدر نفسه الذي تقرأ منه صفحتها العامة.
   كانت هذه الصفحة تقول «نشط حتى كذا» وتعرض فاتورة، ولا تذكر ما اشتري:
   يرى الطالب حالة وتاريخا ثم يبحث عن دروسه في قائمة جانبية. */
$CI->load->model('taqdar_site_model', 'tq_site_m');
$tq_bundle = $tq_plan ? $CI->tq_site_m->bundle_by_code($tq_plan['code']) : null;

/* آخر فاتورة غير مدفوعة: مرجع الحوالة رقمها، وبدونه تصل حوالة
   بلا اسم يطابق فيفتح الاشتراك بالتخمين أو لا يفتح.
   و`unpaid` بالحرف لا «ليست مدفوعة»: الفاتورة المستردة ليست مدفوعة أيضا،
   ولا يطلب من صاحبها أن يحول قيمتها من جديد. */
$tq_due = null;
foreach ((array) $invoices as $tq_i) {
    if ($tq_i['status'] === 'unpaid') { $tq_due = $tq_i; break; }
}

/* زر الدفع بالبطاقة يعرض إن كانت البوابة مضبوطة وحدها — والقرار من
   المتحكم (`Taqdar_tap_model::ready()`). */
$tq_card = !empty($tq_card);

/* لون الحالة ليس زينة: «بانتظار السداد» و«نشط» يقرآن في لمحة واحدة،
   وسطر نصي واحد يجعلهما متساويين في العين. والأصناف من مفردات الشارات
   القائمة لا من عائلة الباستيل — الباستيل يعبئ ولا يضبط لون الحبر. */
$tq_tone = array('pending' => 'due', 'active' => 'mastered',
                 'cancelled' => 'idle', 'expired' => 'late');

$tq_nav   = 'subscription';
$tq_role  = 'student';
$tq_title = t('اشتراكي');
$tq_sub   = t('حالة اشتراكك وتاريخ انتهائه وفواتيرك.');
$tq_icon  = 'wallet';

include 'portal_open.php';
?>

<div class="tq-stack">

    <?php if ($flash = $this->session->flashdata('flash_message')): ?>
        <div class="tq-alert tq-alert--ok"><?php echo html_escape($flash); ?></div>
    <?php endif; ?>
    <?php if ($err = $this->session->flashdata('error_message')): ?>
        <div class="tq-alert tq-alert--no"><?php echo html_escape($err); ?></div>
    <?php endif; ?>

    <?php
    /* نتيجة الاختبار التشخيصي — هنا لا في بند دائم في القائمة.
       الاختبار يؤدى مرة، وهذه هي الشاشة التي يقرر فيها الطالب أمر باقته:
       وهو السؤال الذي أجاب عنه الاختبار. وسطر واحد يكفي — تفصيله في
       صفحته، والرابط إليها. */
    $tq_lv = isset($tq_level) ? $tq_level : null;
    if ($tq_lv && !empty($tq_lv['result_level'])):
        $tq_lvm = Taqdar_diag_model::levels();
        $tq_lvk = (string) $tq_lv['result_level'];
        $tq_lvl = isset($tq_lvm[$tq_lvk]) ? $tq_lvm[$tq_lvk]['label'] : $tq_lvk;
    ?>
        <div class="tq-card tq-card--panel">
            <div class="tq-row tq-row--between">
                <div>
                    <span class="tq-eyebrow"><?php echo t('اختبار تحديد المستوى'); ?></span>
                    <p class="tq-card__title" style="margin:0">موضعك: <?php echo html_escape($tq_lvl); ?></p>
                    <p class="tq-caption" style="margin:0">
                        <span class="tq-ltr"><?php echo (int) $tq_lv['score']; ?></span>
                        <?php echo t('من'); ?> <span class="tq-ltr"><?php echo (int) $tq_lv['total']; ?></span> <?php echo t('اجابة صحيحة'); ?>
                    </p>
                </div>
                <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('student/placement'); ?>">
                    <?php echo t('التفاصيل والباقة الموصى بها'); ?>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$current): ?>

        <div class="tq-card tq-card--panel">
            <div class="tq-empty">
                <span class="tq-icon-box tq-pastel--sky" style="inline-size:64px;block-size:64px" aria-hidden="true">
                    <?php echo tq_icon('wallet', 30); ?>
                </span>
                <p class="tq-empty__title"><?php echo t('لا اشتراك نشط'); ?></p>
                <p class="tq-empty__text">
                    <?php echo t('يمكنك تصفح الدروس المعلمة تجريبية، ويفتح الاشتراك المدفوع بقية المحتوى.'); ?>
                </p>
                <a class="tq-btn tq-btn--primary" href="<?php echo base_url('plans'); ?>"><?php echo t('اطلع على الباقات'); ?></a>
            </div>
        </div>

    <?php else: ?>

        <div class="tq-card tq-card--panel tqs-head">
            <div class="tqs-head__top">
                <div class="tqs-head__b">
                    <span class="tq-eyebrow"><?php echo t('باقتك الحالية'); ?></span>
                    <h2 class="tq-card__title"><?php echo html_escape($current['plan_name']); ?></h2>
                </div>
<?php /* `$labels[$eff]` بلا حارس: `subscriptions.status` عمود نصي قد يحمل
                         حالة لا يعرفها هذا الجدول (يكتبها الكرون أو بوابة دفع)، فتسقط
                         البطاقة بتنبيه مفتاح غير معرف — والشارة بجوارها محروسة بـ`??`
                         أصلا، فالحارسان يتفقان الآن. */ ?>
                <span class="tq-badge tq-badge--<?php echo $tq_tone[$eff] ?? 'idle'; ?>">
                    <?php echo html_escape($labels[$eff] ?? (string) $eff); ?>
                </span>
            </div>

            <?php
            /* الحقائق الأربع في صف بطاقات لا في `<dl>` عارية: المصطلح
               فوق قيمته، والقيمة بخط الأرقام — فتمسح العين الصف مرة. */
            $tq_facts = array(
                array(t('القيمة'), number_format(((int) $current['price']) / 100, 2) . t('ر.س'), 'wallet'),
            );
            if ($current['started_at']) {
                $tq_facts[] = array(t('بدأ في'), date('Y-m-d', strtotime($current['started_at'])), 'calendar');
            }
            if ($current['ends_at']) {
                $tq_facts[] = array($eff === 'cancelled' ? t('صالح حتى') : t('ينتهي في'),
                                    date('Y-m-d', strtotime($current['ends_at'])), 'clock');
            }
            if ($tq_due) {
                $tq_facts[] = array(t('مرجع الفاتورة'), $tq_due['invoice_no'], 'file');
            }
            ?>
            <dl class="tqs-facts">
                <?php foreach ($tq_facts as $f): ?>
                    <div class="tqs-facts__i">
                        <span class="tqs-facts__ico" aria-hidden="true"><?php echo tq_icon($f[2], 17); ?></span>
                        <div>
                            <dt><?php echo html_escape($f[0]); ?></dt>
                            <dd class="tq-ltr" dir="ltr"><?php echo html_escape($f[1]); ?></dd>
                        </div>
                    </div>
                <?php endforeach; ?>
            </dl>

            <?php if ($tq_trial && in_array($eff, array('active', 'cancelled'), true)): ?>
                <p class="tq-caption">
                    <?php echo t('هذه باقة تجريبية: تفتح الدروس المعلمة تجريبية وحدها، وبقية الدروس تبقى مقفلة حتى تشترك في باقة مدفوعة.'); ?>
                </p>
            <?php endif; ?>

            <?php if ($eff === 'pending'): ?>
                <p class="tq-caption">
                    <?php echo t('صدرت فاتورتك وتنتظر السداد بالتحويل البنكي، ثم يفعل اشتراكك يدويا بعد التحقق من وصول الحوالة.'); ?>
                </p>
            <?php elseif ($eff === 'active'): ?>
                <p class="tq-caption">
                    اشتراكك لا يجدد تلقائيا ولا يخصم منك شيء بلا طلبك.
                    <?php if ($tq_trial): ?>
                        وللانتقال إلى باقة مدفوعة أوقف التجربة أولا ثم اختر باقتك.
                    <?php endif; ?>
                </p>
                <?php /* الإلغاء فعل لا يسترد، فيكون POST — ورابط GET ينفذ بمجرد جلبه. */ ?>
                <div class="tqs-acts">
                    <a class="tq-btn tq-btn--primary tq-btn--sm" href="<?php echo base_url('student/bundle'); ?>">
                        <?php echo t('افتح محتوى الباقة'); ?>
                    </a>
                    <form method="post" action="<?php echo base_url('student/subscription_cancel'); ?>">
                        <button type="submit" class="tq-btn tq-btn--secondary tq-btn--sm">
                            <?php echo $tq_trial ? t('إيقاف التجربة') : t('إيقاف التجديد'); ?>
                        </button>
                    </form>
                </div>
            <?php elseif ($eff === 'cancelled'): ?>
                <p class="tq-caption">
                    <?php echo t('أوقفت التجديد، ويبقى اشتراكك صالحا حتى تاريخ انتهائه أعلاه. ويمكنك من الآن اختيار باقة أخرى من صفحة الباقات.'); ?>
                </p>
                <div class="tqs-acts">
                    <a class="tq-btn tq-btn--primary tq-btn--sm" href="<?php echo base_url('student/bundle'); ?>">
                        <?php echo t('افتح محتوى الباقة'); ?>
                    </a>
                    <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo base_url('plans'); ?>"><?php echo t('الباقات'); ?></a>
                </div>
            <?php elseif ($eff === 'expired'): ?>
                <p class="tq-caption"><?php echo t('انتهت مدة هذا الاشتراك. يمكنك الاشتراك من جديد متى شئت.'); ?></p>
                <div class="tqs-acts">
                    <a class="tq-btn tq-btn--primary tq-btn--sm" href="<?php echo base_url('plans'); ?>"><?php echo t('الباقات'); ?></a>
                </div>
            <?php endif; ?>
        </div>

    <?php endif; ?>

    <?php if ($eff === 'pending' && $tq_due): ?>
        <div class="tq-card tq-card--panel">
            <div class="tq-card__head">
                <h2 class="tq-card__title"><?php echo t('كيف تفعل اشتراكك'); ?></h2>
            </div>

            <?php if ($tq_card): ?>
                <?php /* الدفع بالبطاقة من هنا أيضا لا من شاشة التأكيد وحدها.
                         والحال التي يسدها هذا الزر تقع فعلا: من اختار التحويل
                         ثم عدل، ومن بدأ الدفع فتردد وأغلق الصفحة — كلاهما له
                         فاتورة معلقة، وبلا هذا الزر لا سبيل إلى دفعها بالبطاقة
                         إلا أن يشترك من جديد. */ ?>
                <p class="tq-caption">
                    <?php echo t('ادفع فاتورتك بالبطاقة فيفعل اشتراكك في لحظته، أو حول قيمتها إلى الحساب أدناه ويفعل بعد التحقق من الحوالة.'); ?>
                </p>
                <div class="tqs-acts">
                    <form method="post" action="<?php echo base_url('student/pay-invoice'); ?>">
                        <?php echo tq_csrf(); ?>
                        <input type="hidden" name="invoice_id" value="<?php echo (int) $tq_due['id']; ?>">
                        <button type="submit" class="tq-btn tq-btn--primary tq-btn--sm">
                            <?php echo tq_icon('card', 16); ?>
                            ادفع الآن بالبطاقة
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <p class="tq-caption">
                    <?php echo t('حول قيمة الفاتورة إلى الحساب أدناه، واكتب رقم الفاتورة في خانة الملاحظات.'); ?>
                </p>
            <?php endif; ?>

            <?php echo tqs_bank_block($tq_due['invoice_no'], (int) $tq_due['total']); ?>
        </div>
    <?php endif; ?>

    <?php if ($current && $tq_bundle && !empty($tq_bundle['subjects'])): ?>
        <?php $tq_t = $tq_bundle['totals']; ?>
        <div class="tq-card tq-card--panel tqb-incl">
            <div class="tq-card__head">
                <h2 class="tq-card__title"><?php echo t('باقتك تشمل'); ?></h2>
                <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('plan/' . $tq_bundle['code']); ?>">
                    <?php echo t('تفاصيل الباقة'); ?>
                </a>
            </div>

            <?php
            echo tqs_stat_strip(array(
                array($tq_t['grades'],   t('صفوف'),    'i-cap'),
                array($tq_t['subjects'], t('مادة'),    'i-book'),
                array($tq_t['units'],    t('وحدة'),    'i-grid'),
                array($tq_t['lessons'],  t('درسا'),    'i-play'),
                array($tq_t['quizzes'],  t('اختبارا'), 'i-clipboard'),
            ), 'tqb-stats');
            ?>

            <ul class="tqb-subj">
                <?php foreach ($tq_bundle['subjects'] as $tq_s): ?>
                    <li class="tqb-subj__i<?php echo $tq_s['ready'] ? '' : ' is-soon'; ?>">
                        <b><?php echo html_escape($tq_s['title']); ?></b>
                        <span>
                            <?php if ($tq_s['ready']): ?>
                                <?php echo (int) $tq_s['lessons']; ?> درسا
                            <?php else: ?>
                                قيد الإعداد
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($eff === 'active' || $eff === 'cancelled'): ?>
                <a class="tq-btn tq-btn--primary" href="<?php echo base_url('student/bundle'); ?>">
                    <?php echo t('افتح محتوى الباقة'); ?>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php
    /* TQ-COURSE-SALE — الكورسات المشتراة مفردة.
       بطاقة مستقلة عن الباقة لا صف فيها: ما اشتري مفردا **لا ينتهي
       بانتهاء الاشتراك**، وعرضه داخل بطاقة الباقة يجعل الطالب يظن أن
       إيقاف تجديده يقفل ما اشتراه. وهو لا يقفل — والصفحة تقول ذلك.

       والمعلقة تعرض مع السارية: فاتورة صدرت ولم تحول هي ما ينتظره
       صاحبها فعلا، وشاشة تخفيها تتركه ينتظر بلا رقم يحول به. */
    $tq_oc = isset($tq_owned_courses) ? (array) $tq_owned_courses : array();
    if ($tq_oc):
        $tq_oc_labels = array('pending' => t('بانتظار السداد'), 'active' => t('مفتوح'),
                              'cancelled' => t('مفتوح حتى أجله'));
        $tq_oc_tones  = array('pending' => 'due', 'active' => 'mastered', 'cancelled' => 'idle');
    ?>
        <div class="tq-card tq-card--panel">
            <div class="tq-card__head">
                <h2 class="tq-card__title"><?php echo t('كورسات اشتريتها مفردة'); ?></h2>
                <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('catalog?type=course'); ?>">
                    <?php echo t('تصفح المزيد'); ?>
                </a>
            </div>

            <p class="tq-caption">
                <?php echo t('هذه مشتراة بذاتها، فلا يقفلها انتهاء اشتراكك في باقة ولا إيقاف تجديده.'); ?>
            </p>

            <ul class="tqb-subj">
                <?php foreach ($tq_oc as $tq_c):
                    $tq_cs_st = (string) $tq_c['status'];
                    /* منته فعليا وإن لم يمر الكرون بعد — كما في بطاقة الباقة. */
                    if (in_array($tq_cs_st, array('active', 'cancelled'), true)
                        && !empty($tq_c['ends_at']) && strtotime($tq_c['ends_at']) < time()) {
                        continue;   // انتهى: لا يعرض على أنه مفتوح
                    }
                ?>
                    <li class="tqb-subj__i">
                        <b>
                            <?php if ($tq_cs_st === 'pending'): ?>
                                <?php echo html_escape($tq_c['title']); ?>
                            <?php else: ?>
                                <a href="<?php echo base_url('student/lesson/' . (int) $tq_c['course_id']); ?>">
                                    <?php echo html_escape($tq_c['title']); ?>
                                </a>
                            <?php endif; ?>
                        </b>
                        <span>
                            <span class="tq-badge tq-badge--<?php echo $tq_oc_tones[$tq_cs_st] ?? 'idle'; ?>">
                                <?php echo html_escape($tq_oc_labels[$tq_cs_st] ?? $tq_cs_st); ?>
                            </span>
                            <?php if ($tq_cs_st === 'pending' && !empty($tq_c['invoice_no'])): ?>
                                <?php /* رقم الفاتورة هو مرجع الحوالة، وبلاه تصل
                                         حوالة بلا اسم يطابق فتفعل بالتخمين أو
                                         لا تفعل. */ ?>
                                <span class="tq-ltr" dir="ltr"><?php
                                    echo html_escape($tq_c['invoice_no']); ?></span>
                            <?php elseif (!empty($tq_c['ends_at'])): ?>
                                حتى <span class="tq-ltr" dir="ltr"><?php
                                    echo html_escape(substr((string) $tq_c['ends_at'], 0, 10)); ?></span>
                            <?php else: ?>
                                وصول دائم
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php
            /* «حول قيمتها» يقال هنا كذلك لا في بطاقة الباقة وحدها: من
               اشترى كورسا بالتحويل ولا اشتراك له لا يرى تلك البطاقة
               أصلا، فلا يصله بيان الحساب. */
            $tq_oc_due = false;
            foreach ($tq_oc as $tq_c) if ((string) $tq_c['status'] === 'pending') $tq_oc_due = true;
            if ($tq_oc_due && !$current):
            ?>
                <p class="tq-caption">
                    <?php echo t('حول قيمة الفاتورة واذكر رقمها في التحويل، ويفتح الكورس بعد التحقق من الحوالة.'); ?>
                </p>
                <?php echo tqs_bank_block(); ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="tq-card tq-card--panel">
        <div class="tq-card__head">
            <h2 class="tq-card__title"><?php echo t('الفواتير'); ?></h2>
        </div>

        <?php if (empty($invoices)): ?>
            <p class="tq-caption"><?php echo t('لا فواتير بعد.'); ?></p>
        <?php else: ?>
            <div class="tq-table-wrap">
                <table class="tq-table">
                    <thead>
                        <tr>
                            <th><?php echo t('رقم الفاتورة'); ?></th>
                            <th><?php echo t('الإجمالي'); ?></th>
                            <th><?php echo t('الحالة'); ?></th>
                            <th><?php echo t('التاريخ'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td><span class="tq-ltr" dir="ltr"><?php echo html_escape($inv['invoice_no']); ?></span></td>
                            <td><span class="tq-ltr" dir="ltr"><?php echo number_format(((int) $inv['total']) / 100, 2); ?></span> <?php echo t('ر.س'); ?></td>
                            <td>
                                <?php
                                $tq_ist = $inv['status'] === 'paid' ? array(t('مدفوعة'), 'mastered')
                                        : ($inv['status'] === 'refunded' ? array(t('مستردة'), 'idle')
                                                                        : array(t('غير مدفوعة'), 'due'));
                                ?>
                                <span class="tq-badge tq-badge--<?php echo $tq_ist[1]; ?>"><?php echo $tq_ist[0]; ?></span>
                            </td>
                            <td><span class="tq-ltr" dir="ltr"><?php echo date('Y-m-d', strtotime($inv['issued_at'])); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php /* TQ-SPAM — كل صف في هذا الجدول خرجت نسخة منه بالبريد: فاتورة
                 صدرت، أو دفعة قبلت، أو اشتراك فعل. ومن لا تصله هذه الرسائل
                 يظن أن شراءه لم يتم. ومطوي لا مفتوح: هذه شاشة سجل، وفيها
                 الحقيقة نفسها معروضة أمامه. */ ?>
        <?php echo tq_spam_notice(array('compact' => true, 'id' => 'tq-spam-inv')); ?>
    </div>
</div>

<?php include 'portal_close.php'; ?>
