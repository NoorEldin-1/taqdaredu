<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * حصص بالطلب.
 *
 * طبقة مستقلة بجوار المنهج لا داخله: المنهج مسجل بلا جدول ولا حجز،
 * والحصة هنا موعد يطلب عند الحاجة. فلا يخلط الاثنان في شاشة واحدة،
 * ولا يشترط أحدهما للآخر.
 *
 * ولا يخصم مبلغ الحصة إلا بعد تأكيد المعلم: الحجز غير المؤكد لا
 * يحتجز عليه مال ولا تجمد به بطاقة. «بانتظار رد المعلم» تعني بالضبط
 * أن شيئا لم يدفع بعد — والوعد صار له سند: الفاتورة لا تصدر إلا عند
 * التأكيد (TQ-SESSION-PAY في `Taqdar_sessions_model`).
 *
 * موصول بالقاعدة: course + category (عدد معلمي كل مادة) · `availability_slots`
 * (المعلمون المتاحون ومواعيدهم) · `tutoring_sessions` (طلبات الطالب وحالتها).
 * وكان هذا الملف يعد الإتاحة «بلا جدول» وهي في `availability_slots` أمامه.
 *
 * **والسعر يعرض قبل الحجز لا بعده.** كان يقال هنا إنه «بلا مصدر»، وصار له
 * مصدر: تسعيرة الإدارة، واستثناء المعلم إن كتب له. وشاشة تعرض معلمين بلا
 * أثمان تجعل الاختيار يقع ثم ينكشف الثمن بعده.
 *
 * ولا تقييمات ولا سنوات خبرة: لا عمود لها في taqd_lms، فلا تعرض أرقام
 * مخترعة بجوار اسم معلم حقيقي.
 */

$tq_uid = (int) $this->session->userdata('user_id');

/* النموذج يحمل عبر get_instance(): العارض في CI3 ينسخ خصائص المتحكم إلى
   المحمل مرة واحدة قبل التصيير، فما حمل بعد بدء التصيير لا يظهر في `$this`. */
$tq_CI = get_instance();
$tq_CI->load->model('taqdar_sessions_model');
$tq_CI->load->model('taqdar_tap_model');
$tq_m = $tq_CI->taqdar_sessions_model;

/* ---- العرض يعرض ولا يكتب --------------------------------------------
   كانت هذه الشاشة تعالج POST بنفسها: تقرأ `tq_action` وتكتب صف الطلب ثم
   تحول، والنموذج يرسل إلى مسار العرض `student/on-demand`. وهي مخالفة
   صريحة لقاعدة المشروع («مسارات الكتابة قبل مسارات العرض») تلتف على
   `write_guard` كله — وصارت أخطر بعد أن صار للطلب ثمن يجمد على صفه.
   والكتابة الآن في `Taqdar::session_request()` و`session_pay()`
   و`session_cancel()`، ومنها يخرج إشعار المعلم أيضا. */

include 'tq_student_styles.php';
include 'tq_student_data.php';

if (!isset($tq_counts)) $tq_counts = tq_s_counts($tq_uid);

$tq_nav   = 'on_demand';
$tq_role  = 'student';
$tq_title = 'حصص بالطلب';
$tq_sub   = 'احجز حصة مباشرة مع معلم متخصص في المادة التي تحتاجها';
$tq_icon  = 'video';

$tq_subjects = tq_s_subject_tutors(5);

$f_subject = (string) $this->input->get('subject', true);

/**
 * المعلمون المتاحون — من `availability_slots`: معلم له موعد مفتوح لم يمض،
 * ومعه مواعيده نفسها ليطلب أحدها مباشرة. من لا موعد له لا يظهر.
 */
$tq_tutors = $tq_m->available_teachers(12, 6, (int) $f_subject);

/** حجوزات الطالب — من `tutoring_sessions` بحالاتها كما في القاعدة. */
$tq_bookings = $tq_m->bookings_for_student($tq_uid);

$tq_cfg  = $tq_m->config();
$tq_paid = $tq_cfg['price'] > 0;               // هل للحصص ثمن أصلا؟
$tq_card = $tq_CI->taqdar_tap_model->ready();  // وهل يستطاع دفعه بالبطاقة؟

$tq_ses_photo = function ($image) {
    return tqs_person_img($image);
};

/** ثمن بالهللات إلى ريال — القسمة على مئة في موضع واحد. */
$tq_sar = function ($halalas) {
    return TQ_LRI . number_format(((int) $halalas) / 100, 2) . TQ_PDI . ' ر.س';
};

/** ما بقي من مهلة الدفع بعبارة تقرأ — «ساعتان» أوضح من طابع زمني. */
$tq_left = function ($deadline) {
    $ts = strtotime((string) $deadline);
    if (!$ts) return '';
    $d = $ts - time();
    if ($d <= 0)      return 'انتهت المهلة';
    if ($d < 3600)    return 'يتبقى ' . max(1, (int) round($d / 60)) . ' دقيقة';
    if ($d < 86400)   return 'يتبقى ' . (int) floor($d / 3600) . ' ساعة';
    return 'يتبقى ' . (int) floor($d / 86400) . ' يوما';
};

include 'portal_open.php';
?>

<?php /* رسالة النتيجة يطبعها `portal_open.php` لكل شاشات البوابة.
         وكانت تطبع هنا مرة ثانية — و`flashdata()` تقرأ ولا تستهلك داخل
         الطلب الواحد، فيرى الطالب «أرسل طلبك» مرتين متتاليتين بشكلين
         مختلفين ويظن أنه أرسل طلبين. */ ?>

<div class="tq-cols">
    <div>

        <!-- البانر الترويجي: الفعل الأساسي الوحيد في الشاشة -->
        <section class="tq-s-banner tq-section tq-enter">
            <div class="tq-s-banner__body">
                <p class="tq-eyebrow">حصص مباشرة</p>
                <h2 class="tq-display" style="margin-block-end:var(--tq-space-s)">تعلم بطريقتك الخاصة</h2>
                <p class="tq-body">
                    احجز حصة مباشرة مع معلم خبير، واحصل على شرح مخصص لاحتياجك أنت —
                    <?php if ($tq_paid): ?>
                        وثمن الحصة مكتوب عند كل معلم، ولا يخصم إلا بعد أن يؤكد موعدك.
                    <?php else: ?>
                        ولا يخصم مبلغ الحصة إلا بعد تأكيد المعلم.
                    <?php endif; ?>
                </p>
                <a class="tq-btn tq-btn--primary" href="<?php echo base_url('student/on-demand#tq-tutors'); ?>">
                    <?php echo tq_icon('calendar'); ?> اطلب حصة الآن
                </a>
            </div>
            <span class="tq-s-banner__art" aria-hidden="true"><?php echo tq_icon('video', 56); ?></span>
        </section>

        <!-- اختر المادة -->
        <section class="tq-section">
            <div class="tq-sectionhead">
                <h2>اختر المادة</h2>
                <?php if ($tq_subjects): ?>
                    <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('categories'); ?>">عرض الكل</a>
                <?php endif; ?>
            </div>

            <?php if (empty($tq_subjects)): ?>
                <div class="tq-card">
                    <?php echo tq_s_empty(
                        'folder', 'sky',
                        'لا مواد بعد',
                        'مواد المنصة تظهر هنا، وتحت كل مادة عدد معلميها لتختار مادتك أولا ثم معلمك.',
                        '', '', true
                    ); ?>
                </div>
            <?php else: ?>
                <div class="tq-s-grid5">
                    <?php foreach ($tq_subjects as $i => $s): ?>
                        <?php $active = ((string) $s['id'] === $f_subject); ?>
                        <a class="tq-card tq-s-course" style="text-align:center;align-items:center<?php echo $active ? ';border:var(--tq-field-border) solid var(--tq-navy)' : ''; ?>"
                           href="<?php echo base_url('student/on-demand?subject=' . $s['id']); ?>"
                           <?php echo $active ? 'aria-current="true"' : ''; ?>>
                            <span class="tq-icon-box tq-pastel tq-pastel--<?php echo tq_pastel($i); ?>" aria-hidden="true">
                                <span class="tq-pastel__icon"><?php echo tq_icon('book'); ?></span>
                            </span>
                            <span class="tq-strong" style="color:var(--tq-navy)"><?php echo html_escape($s['name']); ?></span>
                            <span class="tq-micro"><?php echo tq_iso($s['tutors'] . ' معلم في المادة'); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php if ($f_subject !== ''): ?>
                    <p style="margin-block-start:var(--tq-space-m)">
                        <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('student/on-demand'); ?>">
                            إلغاء تصفية المادة
                        </a>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <!-- معلمون متاحون الآن: من فتح وقتا فعلا، بمواعيده هو -->
        <section class="tq-section" id="tq-tutors">
            <div class="tq-sectionhead">
                <h2>معلمون متاحون الآن</h2>
                <?php if ($tq_tutors): ?>
                    <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_tutors) . TQ_PDI; ?></span>
                <?php endif; ?>
            </div>

            <?php if (empty($tq_tutors)): ?>
                <div class="tq-card">
                    <?php /* كان الزر «نبهني عند توفر معلم» يقود إلى الإعدادات، وليس في
                             الإعدادات — ولا في `notify_types()` — تنبيه بهذا المعنى. فالزر
                             يعد بتنبيه لا يوجد من يرسله، والوجهة لا تحوي ما يبحث عنه.
                             والبديل فعل قائم فعلا: مراسلة معلم من صندوق الرسائل. */ ?>
                    <?php echo tq_s_empty(
                        'users', 'mint',
                        'لا معلم متاح الآن',
                        'حين يفتح المعلمون أوقاتهم يظهر كل واحد هنا باسمه ومادته ومواعيده المتاحة، وبجواره زر طلب مباشر. وحتى ذلك الحين يمكنك مراسلة معلم مادتك مباشرة.',
                        'راسل معلمك',
                        base_url('student/messages')
                    ); ?>
                </div>
            <?php else: ?>
                <?php foreach ($tq_tutors as $t): ?>
                    <article class="tq-card" style="margin-block-end:var(--tq-space-l)">
                        <div class="tq-row" style="gap:var(--tq-space-l);align-items:flex-start">
                            <img class="tq-avatar tq-avatar--lg" src="<?php echo html_escape($tq_ses_photo($t['image'])); ?>"
                                 alt="<?php echo html_escape('صورة ' . $t['name']); ?>">

                            <div style="flex:1;min-inline-size:0">
                                <h3 class="tq-strong" style="margin:0;color:var(--tq-navy)"><?php echo html_escape($t['name']); ?></h3>
                                <?php if ($t['subject'] !== '' || $t['title'] !== ''): ?>
                                    <p class="tq-micro" style="margin:0">
                                        <?php echo html_escape($t['subject'] !== '' ? $t['subject'] : $t['title']); ?>
                                    </p>
                                <?php endif; ?>
                                <p class="tq-micro" style="margin-block-start:var(--tq-space-xs)">
                                    <?php echo tq_iso(count($t['slots']) . ' موعد متاح'); ?>
                                    <?php echo tq_iso(' · ' . $t['slots'][0]['minutes'] . ' دقيقة للحصة'); ?>
                                </p>
                            </div>

                            <?php /* الثمن بجوار الاسم لا في خطوة تالية: هو أول ما
                                     يقارن به الطالب بين معلمين، وإخفاؤه إلى ما بعد
                                     الاختيار يجعل الاختيار يقع ثم ينكشف ثمنه. */ ?>
                            <?php if ((int) $t['pricing']['price'] > 0): ?>
                                <div style="text-align:center;flex:0 0 auto">
                                    <span class="tq-strong" style="color:var(--tq-navy);font-size:1.15rem;display:block">
                                        <?php echo $tq_sar($t['pricing']['price']); ?>
                                    </span>
                                    <span class="tq-micro">للحصة</span>
                                </div>
                            <?php else: ?>
                                <?php echo tq_badge('mastered', 'مجانية'); ?>
                            <?php endif; ?>
                        </div>

                        <form method="post" action="<?php echo base_url('student/sessions/request'); ?>"
                              class="tq-row" style="gap:var(--tq-space-m);flex-wrap:wrap;margin-block-start:var(--tq-space-l)">
                            <?php echo tq_csrf(); ?>
                            <input type="hidden" name="subject" value="<?php echo html_escape($f_subject); ?>">

                            <label class="tq-sr" for="tq-slot-<?php echo (int) $t['id']; ?>">
                                <?php echo html_escape('اختر موعدا مع ' . $t['name']); ?>
                            </label>
                            <select class="tq-select" name="slot_id" id="tq-slot-<?php echo (int) $t['id']; ?>"
                                    style="flex:1;min-inline-size:16rem" required>
                                <?php foreach ($t['slots'] as $sl): ?>
                                    <option value="<?php echo (int) $sl['id']; ?>"><?php echo html_escape($sl['when_text']); ?></option>
                                <?php endforeach; ?>
                            </select>

                            <button class="tq-btn tq-btn--mastery tq-btn--sm" type="submit">اطلب هذا الموعد</button>

                            <?php if ((int) $t['pricing']['price'] > 0): ?>
                                <span class="tq-micro" style="flex-basis:100%">
                                    الطلب مجاني ولا يخصم منك شيء. يؤكد المعلم أولا، ثم تدفع
                                    <?php echo $tq_sar($t['pricing']['price']); ?> خلال
                                    <?php echo tq_iso($tq_cfg['pay_hours'] . ' ساعة'); ?> لتثبيت الموعد.
                                </span>
                            <?php endif; ?>
                        </form>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

    </div>

    <aside class="tq-aside">

        <!-- كيف تعمل حصص بالطلب؟ -->
        <section class="tq-card tq-card--panel">
            <div class="tq-card__head"><h2 class="tq-card__title">كيف تعمل حصص بالطلب؟</h2></div>
            <ol class="tq-s-steps">
                <?php
                /* الخطوات تصف ما يقع فعلا لا ما يحسن وقعه: كانت أربعا تقفز
                   من «حدد الوقت» إلى «ابدأ الحصة» — فلا رد معلم ولا دفع.
                   والطالب الذي لا يعرف أن معلمه يرد أولا يقرأ «بانتظار رد
                   المعلم» على أنها عطل، والذي لا يعرف أن ثمة دفعا يفاجأ
                   بفاتورة. */
                $steps = [
                    ['اختر معلما وموعدا', 'تصفح المعلمين المتاحين، وثمن الحصة مكتوب عند كل واحد.'],
                    ['يرد معلمك',        'يؤكد الموعد أو يعتذر عنه. ولا يخصم منك شيء في هذه الخطوة.'],
                ];
                if ($tq_paid) {
                    $steps[] = ['ادفع لتثبيت الموعد',
                        'بعد التأكيد تصلك فاتورة الحصة. تدفعها خلال '
                        . $tq_cfg['pay_hours'] . ' ساعة فيثبت الموعد لك وحدك.'];
                }
                $steps[] = ['ادخل الحصة',
                    'يفتح الرابط هنا قبل الموعد بـ' . $tq_cfg['lead_min']
                    . ' دقيقة، ويغلق حين يعلن معلمك انتهاءها.'];
                foreach ($steps as $i => $s):
                    ?>
                    <li class="tq-s-step">
                        <span class="tq-s-step__n" aria-hidden="true"><?php echo TQ_LRI . ($i + 1) . TQ_PDI; ?></span>
                        <span>
                            <span class="tq-s-step__t"><?php echo html_escape($s[0]); ?></span>
                            <span class="tq-s-step__d"><?php echo html_escape($s[1]); ?></span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ol>
            <p class="tq-micro" style="margin-block-start:var(--tq-space-l);margin-block-end:0">
                <?php if ($tq_paid): ?>
                    لا يخصم مبلغ الحصة إلا بعد تأكيد المعلم — والطلب نفسه مجاني.
                    وإن اعتذر معلمك أو مضت مهلة الدفع فلا يخصم شيء أصلا.
                <?php else: ?>
                    الحصص مجانية حاليا: تطلب موعدا، ويؤكده معلمك، وتدخل.
                <?php endif; ?>
            </p>
        </section>

        <!-- حجوزاتك القادمة -->
        <section class="tq-card tq-card--panel">
            <div class="tq-card__head">
                <h2 class="tq-card__title">حجوزاتك القادمة</h2>
                <?php if ($tq_bookings): ?>
                    <a class="tq-caption" href="<?php echo base_url('student/calendar'); ?>">عرض الكل</a>
                <?php endif; ?>
            </div>

            <?php if (empty($tq_bookings)): ?>
                <?php echo tq_s_empty(
                    'calendar', 'peach',
                    'لا حجوزات بعد',
                    'كل حجز تطلبه يظهر هنا بموعده ومعلمه وحالته: بانتظار التأكيد، أو مؤكد، أو اعتذر عنه المعلم.',
                    '', '', true
                ); ?>
            <?php else: ?>
                <ul class="tq-s-list">
                    <?php foreach ($tq_bookings as $b): ?>
                        <?php $badge = $tq_m->status_badge($b['status']); ?>
                        <li class="tq-s-item tq-s-item--stack">
                            <div class="tq-row" style="gap:var(--tq-space-m);inline-size:100%">
                                <img class="tq-avatar" src="<?php echo html_escape($tq_ses_photo($b['image'])); ?>"
                                     alt="<?php echo html_escape('صورة ' . $b['tutor']); ?>">
                                <span class="tq-s-item__body" style="flex:1;min-inline-size:0">
                                    <span class="tq-s-item__t tq-s-trunc"><?php echo html_escape($b['subject']); ?></span>
                                    <span class="tq-s-item__s tq-s-trunc"><?php echo html_escape($b['tutor']); ?></span>
                                    <span class="tq-s-item__s"><?php echo tq_iso($b['when_text']); ?></span>
                                </span>
                                <?php echo tq_badge($badge[0], $badge[1]); ?>
                            </div>

                            <?php /* الثمن يقرأ في البطاقة نفسها: «كم دفعت في هذه
                                     الحصة» سؤال يطرح بعد الحجز لا قبله فقط. */ ?>
                            <?php if ((int) $b['price'] > 0): ?>
                                <p class="tq-micro" style="margin:var(--tq-space-xs) 0 0">
                                    <?php echo $tq_sar($b['price']); ?>
                                    <?php if ($b['invoice_no'] !== ''): ?>
                                        · فاتورة <span class="tq-ltr"><?php echo html_escape($b['invoice_no']); ?></span>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>

                            <?php /* بانتظار الدفع: هذه هي البطاقة التي تقرر مصير
                                     الحصة. المهلة تعرض بما بقي منها لا بطابع زمني،
                                     ويقال صراحة ما يقع إن مضت — الموعد يعود لغيره،
                                     وطالب لم يقل له ذلك يظن حجزه محفوظا إلى الأبد. */ ?>
                            <?php if ($b['needs_pay']): ?>
                                <div class="tq-pastel tq-pastel--peach" style="margin-block-start:var(--tq-space-s);padding:var(--tq-space-m)">
                                    <p class="tq-pastel__body" style="margin:0 0 var(--tq-space-s);font-size:.85rem">
                                        أكد المعلم الموعد. ادفع
                                        <strong><?php echo $tq_sar($b['invoice_total'] ?: $b['price']); ?></strong>
                                        لتثبيته
                                        <?php if ($b['pay_deadline']): ?>
                                            — <strong><?php echo tq_iso($tq_left($b['pay_deadline'])); ?></strong>،
                                            وبعدها يعود الموعد متاحا لغيرك.
                                        <?php endif; ?>
                                    </p>

                                    <?php if ($tq_card): ?>
                                        <form method="post" action="<?php echo base_url('student/sessions/pay'); ?>" style="margin:0">
                                            <?php echo tq_csrf(); ?>
                                            <input type="hidden" name="session_id" value="<?php echo (int) $b['id']; ?>">
                                            <button class="tq-btn tq-btn--primary tq-btn--sm tq-btn--block" type="submit">
                                                <?php echo tq_icon('card', 16); ?> ادفع الآن بالبطاقة
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <?php /* بلا مفاتيح بوابة لا يعرض زر يقود إلى رفض:
                                                 يقال الطريق القائم فعلا. */ ?>
                                        <p class="tq-micro" style="margin:0">
                                            الدفع بالبطاقة غير متاح الآن. حول المبلغ بنكيا بمرجع رقم الفاتورة،
                                            <a href="<?php echo base_url('student/payments'); ?>">وبيانات الحساب هنا</a>.
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php /* باب الحصة.
                                     الخطوة الرابعة في «كيف تعمل حصص بالطلب؟» تقول «انضم للحصة»
                                     ولم يكن في الشاشة كلها ما ينضم به: يؤكد المعلم الموعد فيرى
                                     الطالب شارة «مؤكد» ولا يعرف أين تعقد. والرابط يضعه المعلم
                                     عند التأكيد، ولا يعرض إلا وقت نفعه — انظر `join_state()`. */ ?>
                            <?php if ($b['can_join']): ?>
                                <a class="tq-btn tq-btn--mastery tq-btn--sm tq-btn--block"
                                   href="<?php echo html_escape($b['meet_url']); ?>"
                                   target="_blank" rel="noopener noreferrer"
                                   style="margin-block-start:var(--tq-space-s)">
                                    <?php echo tq_icon('video', 16); ?>
                                    <?php echo $b['status'] === 'live' ? 'ادخل الحصة الجارية' : 'ادخل الحصة'; ?>
                                    <span class="tq-sr">— يفتح في نافذة جديدة</span>
                                </a>
                            <?php elseif ($b['note'] !== '' && !$b['needs_pay']): ?>
                                <?php /* والنص من `join_state()` لا من فروع مكتوبة هنا:
                                         «لم يدفع» غير «لا رابط» غير «انتهت»، والشاشة
                                         التي تخلطها تقول للطالب شيئا لا يطابق حاله. */ ?>
                                <p class="tq-micro" style="margin:var(--tq-space-xs) 0 0">
                                    <?php echo html_escape($b['note']); ?>
                                </p>
                            <?php endif; ?>

                            <?php if ($b['cancel_reason'] !== ''
                                      && in_array($b['status'], ['declined', 'expired', 'refunded'], true)): ?>
                                <p class="tq-micro" style="margin:var(--tq-space-xs) 0 0;color:var(--tq-text3)">
                                    <?php echo html_escape($b['cancel_reason']); ?>
                                </p>
                            <?php endif; ?>

                            <?php /* الإلغاء قبل الدفع وحده. وبعده يوجه إلى الإدارة:
                                     زر يلغي حصة دفع ثمنها بلا رد يترك الطالب بلا
                                     حصة وبلا مال. */ ?>
                            <?php if ($b['can_cancel']): ?>
                                <form method="post" action="<?php echo base_url('student/sessions/cancel'); ?>"
                                      class="tq-form-inline" style="margin-block-start:var(--tq-space-xs)"
                                      data-tq-confirm-title="إلغاء هذا الحجز؟"
                                      data-tq-confirm="يعود الموعد متاحا لغيرك، ويصل معلمك أنك ألغيت."
                                      data-tq-confirm-note="لم يخصم منك شيء بعد، فالإلغاء الآن بلا تكلفة."
                                      data-tq-confirm-ok="ألغ الحجز"
                                      data-tq-confirm-tone="danger">
                                    <?php echo tq_csrf(); ?>
                                    <input type="hidden" name="session_id" value="<?php echo (int) $b['id']; ?>">
                                    <button class="tq-btn tq-btn--ghost tq-btn--sm tq-btn--block" type="submit">
                                        إلغاء الحجز
                                    </button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- عرض خاص: عرض حقيقي أو لا شيء — ولا خصم مخترع لملء فراغ. -->
        <section class="tq-card tq-card--panel tq-pastel tq-pastel--lilac">
            <div class="tq-card__head">
                <h2 class="tq-card__title tq-pastel__title">عرض خاص</h2>
                <span class="tq-pastel__icon" aria-hidden="true"><?php echo tq_icon('star', 24); ?></span>
            </div>
            <p class="tq-pastel__body">
                لا يوجد عرض سار الآن. العروض على باقات الحصص تظهر هنا فور إطلاقها.
            </p>
            <a class="tq-btn tq-btn--secondary tq-btn--block" href="<?php echo base_url('plans'); ?>">
                عرض الباقات
            </a>
        </section>

    </aside>
</div>

<?php include 'portal_close.php'; ?>
