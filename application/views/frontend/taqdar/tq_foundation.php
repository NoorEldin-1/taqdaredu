<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * TQ-FOUNDATION — قسم التأسيس في بوابة الطالب.
 *
 * قسم مستقل عن الباقات والمسارات: لا محتوى مسجلا ولا منهج صف — وقت معلم
 * يحجز بالساعة، وحده.
 *
 * **ولماذا شاشة ثانية بجوار «حصص بالطلب»؟** لأنهما سؤالان لا سؤال:
 *
 *   حصص بالطلب   «اشرح لي درس اليوم» — منهج صفي، في مادتي
 *   التأسيس      «علمني القراءة من اولها» — لا صف لي فيه ولا مادة
 *
 * وشاشة واحدة تخلطهما تفعل بالطالب ما فعلته صفحة الكتالوج القديمة حين
 * خلطت «ماذا تقدم المنصة؟» بـ«بكم؟»: مرشحات لا تشبه بعضها، وقائمة يقرؤها
 * من يبحث عن أحدهما فيجد نصفها لا يعنيه. **والاخطر ان ترشيح الصف يمحو
 * التأسيس من الشاشة اصلا**: مواعيد التأسيس بلا صف بحكم الحفظ، وطالب في
 * الرابع لا يعنيه صفه حين يطلب ان يؤسس في الانجليزية.
 *
 * والترشيح كله في `available_teachers($kind, $track)` اي في الاستعلام لا
 * هنا: نسختان من قاعدة واحدة تفترقان عند اول تعديل — وهي قاعدة الكتالوج
 * نفسها.
 *
 * **والكتابة ليست هنا**: الطلب والدفع والالغاء تذهب الى
 * `student/sessions/request|pay|cancel` — المسارات الثلاثة نفسها التي
 * تخدم حصص المنهج، ولا محرك ثان. و`back=foundation` يعيد صاحبه الى هذه
 * الشاشة لا الى اختها.
 */

$tq_uid = (int) $this->session->userdata('user_id');

/* النموذج يحمل عبر get_instance(): العارض في CI3 ينسخ خصائص المتحكم إلى
   المحمل مرة واحدة قبل التصيير، فما حمل بعد بدء التصيير لا يظهر في `$this`. */
$tq_CI = get_instance();
$tq_CI->load->model('taqdar_sessions_model');
$tq_CI->load->model('taqdar_foundation_model');
$tq_CI->load->model('taqdar_tap_model');
$tq_m   = $tq_CI->taqdar_sessions_model;
$tq_fnd = $tq_CI->taqdar_foundation_model;

include 'tq_student_styles.php';
include 'tq_student_data.php';

if (!isset($tq_counts)) $tq_counts = tq_s_counts($tq_uid);

$tq_nav   = 'foundation';
$tq_role  = 'student';
$tq_title = t('التأسيس');
$tq_sub   = t('حصص مباشرة فردية تبدأ من حيث أنت — بلا صف ولا منهج');
$tq_icon  = 'graduation';

$tq_tracks = $tq_fnd->published();
$f_track   = (int) $this->input->get('track');
if ($f_track > 0 && !isset($tq_tracks[$f_track])) $f_track = 0;

/* المعلمون المتاحون في التأسيس — ولا ترشيح بالصف: التأسيس مستوى لا مقرر،
   ومن يرشح بالصف يمحو من الشاشة معلما يصلح لصاحبها تماما. */
$tq_tutors = $tq_m->available_teachers(12, 6, 0, 0, 'foundation', $f_track);

/* حجوزات هذا القسم وحدها: شاشة تخلط حجوزات التأسيس بحجوزات المنهج تجعل
   الطالب يبحث عن حصته في قائمتين. */
$tq_bookings = array();
foreach ($tq_m->bookings_for_student($tq_uid, 30) as $tq_b) {
    if (($tq_b['kind'] ?? '') === 'foundation') $tq_bookings[] = $tq_b;
}

$tq_cfg  = $tq_m->config();
$tq_card = $tq_CI->taqdar_tap_model->ready();

$tq_ses_photo = function ($image) { return tqs_person_img($image); };

/** ثمن بالهللات إلى ريال — القسمة على مئة في موضع واحد. */
$tq_sar = function ($halalas) {
    return TQ_LRI . number_format(((int) $halalas) / 100, 2) . TQ_PDI . t(' ر.س');
};

/** ما بقي من مهلة الدفع بعبارة تقرأ — «ساعتان» أوضح من طابع زمني. */
$tq_left = function ($deadline) {
    $ts = strtotime((string) $deadline);
    if (!$ts) return '';
    $d = $ts - time();
    if ($d <= 0)    return t('انتهت المهلة');
    if ($d < 3600)  return t('يتبقى ') . max(1, (int) round($d / 60)) . t(' دقيقة');
    if ($d < 86400) return t('يتبقى ') . (int) floor($d / 3600) . t(' ساعة');
    return t('يتبقى ') . (int) floor($d / 86400) . t(' يوما');
};

/** سعر المسار كما يعلن — والحاسبة واحدة للشاشة وللفاتورة. */
$tq_price_of = function ($track_id) use ($tq_fnd) {
    return $tq_fnd->pricing_for($track_id, 0);
};

include 'portal_open.php';
?>

<div class="tq-cols">
    <div>

        <!-- البانر: ما هذا القسم، وبم يفترق عما جاوره -->
        <section class="tq-s-banner tq-section tq-enter">
            <div class="tq-s-banner__body">
                <p class="tq-eyebrow"><?php echo t('قسم مستقل'); ?></p>
                <h2 class="tq-display" style="margin-block-end:var(--tq-space-s)"><?php echo t('ابدأ من الأساس'); ?></h2>
                <p class="tq-body">
                    <?php echo t('حصص مباشرة فردية مع معلم متخصص في التأسيس — تبدأ من حيث أنت لا من حيث صفك.'); ?>
                    <?php /* الفرق يقال صراحة: القسم منفصل عن الباقات
                             والمسارات، ومن لم يقل له ذلك يظنه محتوى في
                             باقته فينتظر ان يفتح له بلا حجز. */ ?>
                    <?php echo t('وهو مستقل عن الباقات والمسارات: تحجز الساعة التي تناسبك وتدفع ثمنها وحدها، ولا يشترط اشتراك.'); ?>
                </p>
                <a class="tq-btn tq-btn--primary" href="#tq-tutors">
                    <?php echo tq_icon('calendar'); ?>
                    <?php echo $tq_tutors ? t('احجز موعدك الآن') : t('اعرض المعلمين المتاحين'); ?>
                </a>
            </div>
            <span class="tq-s-banner__art" aria-hidden="true"><?php echo tq_icon('graduation', 56); ?></span>
        </section>

        <!-- اختر المسار -->
        <section class="tq-section">
            <div class="tq-sectionhead">
                <h2><?php echo t('اختر مسار التأسيس'); ?></h2>
            </div>

            <?php if (empty($tq_tracks)): ?>
                <div class="tq-card">
                    <?php echo tq_s_empty(
                        'graduation', 'sky',
                        t('لا مسارات تأسيس بعد'),
                        t('مسارات التأسيس تظهر هنا حين تفتحها الإدارة، وتحت كل مسار معلموه ومواعيدهم.'),
                        '', '', true
                    ); ?>
                </div>
            <?php else: ?>
                <div class="tq-s-grid5">
                    <?php foreach ($tq_tracks as $tq_tid => $tq_t): ?>
                        <?php
                        $tq_active = ($f_track === (int) $tq_tid);
                        $tq_p      = $tq_price_of((int) $tq_tid);
                        ?>
                        <a class="tq-card tq-s-course" style="text-align:center;align-items:center<?php echo $tq_active ? ';border:var(--tq-field-border) solid var(--tq-navy)' : ''; ?>"
                           href="<?php echo base_url('student/foundation?track=' . (int) $tq_tid); ?>#tq-tutors"
                           <?php echo $tq_active ? 'aria-current="true"' : ''; ?>>
                            <span class="tq-icon-box tq-pastel tq-pastel--<?php echo tq_pastel((int) $tq_tid); ?>" aria-hidden="true">
                                <span class="tq-pastel__icon"><?php echo tq_icon('graduation'); ?></span>
                            </span>
                            <span class="tq-strong" style="color:var(--tq-navy)"><?php echo html_escape($tq_t['name']); ?></span>
                            <?php /* الثمن على البطاقة لا بعد الاختيار: هو
                                     أول ما يقارن به، وإخفاؤه يجعل الاختيار
                                     يقع ثم ينكشف ثمنه. */ ?>
                            <span class="tq-micro">
                                <?php echo (int) $tq_p['price'] > 0
                                    ? $tq_sar($tq_p['price']) . t(' للحصة')
                                    : t('مجانية'); ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php if ($f_track > 0): ?>
                    <?php $tq_cur = $tq_tracks[$f_track]; ?>
                    <div class="tq-card" style="margin-block-start:var(--tq-space-l)">
                        <h3 class="tq-strong" style="margin:0 0 var(--tq-space-xs);color:var(--tq-navy)">
                            <?php echo html_escape($tq_cur['name']); ?>
                        </h3>
                        <?php if ($tq_cur['description'] !== ''): ?>
                            <p class="tq-body" style="margin:0"><?php echo html_escape($tq_cur['description']); ?></p>
                        <?php endif; ?>
                        <?php if ($tq_cur['outcomes']): ?>
                            <p class="tq-micro" style="margin:var(--tq-space-m) 0 var(--tq-space-xs)"><?php echo t('ماذا تتقن بعده'); ?></p>
                            <ul class="tq-s-list">
                                <?php foreach ($tq_cur['outcomes'] as $tq_o): ?>
                                    <li class="tq-s-item"><span class="tq-s-item__body"><?php echo html_escape($tq_o); ?></span></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <p style="margin:var(--tq-space-m) 0 0">
                            <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('student/foundation'); ?>#tq-tutors">
                                <?php echo t('اعرض كل المسارات'); ?>
                            </a>
                        </p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <!-- معلمون متاحون: من فتح وقت تأسيس فعلا، بمواعيده هو -->
        <section class="tq-section" id="tq-tutors">
            <div class="tq-sectionhead">
                <h2><?php echo t('معلمون متاحون الآن'); ?></h2>
                <?php if ($tq_tutors): ?>
                    <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_tutors) . TQ_PDI; ?></span>
                <?php endif; ?>
            </div>

            <?php /* والفرق عن «حصص بالطلب» يقال هنا كذلك: الطالب الذي
                     يعرف أن مواعيده ترشح بصفه هناك يسأل أين ذهب الترشيح. */ ?>
            <p class="tq-caption" style="margin-block-end:var(--tq-space-l)">
                <?php echo t('مواعيد التأسيس مفتوحة لكل الصفوف — المعلم يؤسسك من حيث أنت، فلا يشترط صفك ولا مادتك.'); ?>
            </p>

            <?php if (empty($tq_tutors)): ?>
                <div class="tq-card">
                    <?php if ($f_track > 0): ?>
                        <?php echo tq_s_empty(
                            'users', 'mint',
                            t('لا معلم فتح وقتا في هذا المسار بعد'),
                            t('اختيارك وصل، ولم يفتح معلمو هذا المسار وقتا في الأيام القادمة. اعرض المسارات كلها لترى من فتح وقته الآن.'),
                            t('اعرض كل المسارات'),
                            base_url('student/foundation') . '#tq-tutors'
                        ); ?>
                    <?php else: ?>
                        <?php echo tq_s_empty(
                            'users', 'mint',
                            t('لا معلم متاح الآن'),
                            t('حين يفتح معلمو التأسيس أوقاتهم يظهر كل واحد هنا باسمه ومساره ومواعيده، وبجواره زر حجز مباشر. وحتى ذلك الحين يمكنك مراسلة الدعم.'),
                            t('راسل الدعم'),
                            base_url('student/messages')
                        ); ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($tq_tutors as $t): ?>
                    <article class="tq-card" style="margin-block-end:var(--tq-space-l)">
                        <div class="tq-row" style="gap:var(--tq-space-l);align-items:flex-start">
                            <img class="tq-avatar tq-avatar--lg" src="<?php echo html_escape($tq_ses_photo($t['image'])); ?>"
                                 alt="<?php echo html_escape(t('صورة ') . $t['name']); ?>">

                            <div style="flex:1;min-inline-size:0">
                                <h3 class="tq-strong" style="margin:0;color:var(--tq-navy)"><?php echo html_escape($t['name']); ?></h3>
                                <?php if ($t['subject'] !== '' || $t['title'] !== ''): ?>
                                    <p class="tq-micro" style="margin:0">
                                        <?php echo html_escape($t['subject'] !== '' ? $t['subject'] : $t['title']); ?>
                                    </p>
                                <?php endif; ?>
                                <p class="tq-micro" style="margin-block-start:var(--tq-space-xs)">
                                    <?php echo tq_iso(count($t['slots']) . t(' موعد متاح')); ?>
                                    <?php echo tq_iso(' · ' . $t['slots'][0]['minutes'] . t(' دقيقة للحصة')); ?>
                                </p>
                            </div>

                            <?php if ((int) $t['pricing']['price'] > 0): ?>
                                <div style="text-align:center;flex:0 0 auto">
                                    <span class="tq-strong" style="color:var(--tq-navy);font-size:1.15rem;display:block">
                                        <?php echo $tq_sar($t['pricing']['price']); ?>
                                    </span>
                                    <span class="tq-micro"><?php echo t('للحصة'); ?></span>
                                </div>
                            <?php else: ?>
                                <?php echo tq_badge('mastered', t('مجانية')); ?>
                            <?php endif; ?>
                        </div>

                        <form method="post" action="<?php echo base_url('student/sessions/request'); ?>"
                              class="tq-row" style="gap:var(--tq-space-m);flex-wrap:wrap;margin-block-start:var(--tq-space-l)">
                            <?php echo tq_csrf(); ?>
                            <?php /* `back` يعيد صاحبه إلى هذه الشاشة لا إلى
                                     «حصص بالطلب»: مسار الكتابة واحد للبابين،
                                     ومن خرج من طلبه إلى شاشة لا يجد فيها
                                     حجزه يظن أن طلبه لم يصل فيعيده. */ ?>
                            <input type="hidden" name="back" value="foundation">
                            <input type="hidden" name="track_id" value="<?php echo (int) $f_track; ?>">

                            <label class="tq-sr" for="tq-fslot-<?php echo (int) $t['id']; ?>">
                                <?php echo html_escape(t('اختر موعدا مع ') . $t['name']); ?>
                            </label>
                            <select class="tq-select" name="slot_id" id="tq-fslot-<?php echo (int) $t['id']; ?>"
                                    style="flex:1;min-inline-size:16rem" required>
                                <?php foreach ($t['slots'] as $sl): ?>
                                    <?php /* اسم المسار على كل خيار حين تعرض
                                             المسارات كلها: معلم يدرس مسارين
                                             وقائمة بلا تمييز تجعل الطالب
                                             يحجز ساعة عربية ليؤسس بالإنجليزية. */ ?>
                                    <option value="<?php echo (int) $sl['id']; ?>">
                                        <?php echo html_escape($sl['when_text']); ?><?php
                                            if ($f_track <= 0 && (string) $sl['track_name'] !== '') {
                                                echo ' — ' . html_escape($sl['track_name']);
                                            }
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <button class="tq-btn tq-btn--mastery tq-btn--sm" type="submit"><?php echo t('اطلب هذا الموعد'); ?></button>

                            <?php if ((int) $t['pricing']['price'] > 0): ?>
                                <span class="tq-micro" style="flex-basis:100%">
                                    <?php echo t('الطلب مجاني ولا يخصم منك شيء. يؤكد المعلم أولا، ثم تدفع ____ خلال ____ لتثبيت الموعد.', array($tq_sar($t['pricing']['price']), tq_iso($tq_cfg['pay_hours'] . t(' ساعة')))); ?>
                                </span>
                            <?php endif; ?>
                        </form>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

    </div>

    <aside class="tq-aside">

        <!-- كيف يعمل التأسيس؟ -->
        <section class="tq-card tq-card--panel">
            <div class="tq-card__head"><h2 class="tq-card__title"><?php echo t('كيف يعمل التأسيس؟'); ?></h2></div>
            <ol class="tq-s-steps">
                <?php
                $steps = array(
                    array(t('اختر مسارك ومعلمك'), t('عربي أو إنجليزي — والثمن مكتوب على كل بطاقة قبل أن تحجز.')),
                    array(t('احجز الساعة التي تناسبك'), t('مواعيد المعلمين معروضة كما فتحوها، وتحجز واحدا منها.')),
                    array(t('يرد معلمك'), t('يؤكد الموعد أو يعتذر عنه. ولا يخصم منك شيء في هذه الخطوة.')),
                );
                $steps[] = array(t('ادفع لتثبيت الموعد'),
                    t('بعد التأكيد تصلك فاتورة الحصة. تدفعها خلال ')
                    . $tq_cfg['pay_hours'] . t(' ساعة فيثبت الموعد لك وحدك.'));
                $steps[] = array(t('ادخل الحصة'),
                    t('يفتح الرابط هنا قبل الموعد بـ') . $tq_cfg['lead_min']
                    . t(' دقيقة، ويغلق حين يعلن معلمك انتهاءها.'));
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
                <?php echo t('والتأسيس خارج الباقات: تدفع ثمن الحصة التي حجزتها وحدها، ولا يشترط اشتراك. وإن اعتذر معلمك أو مضت مهلة الدفع فلا يخصم شيء أصلا.'); ?>
            </p>
        </section>

        <!-- حجوزاتك في التأسيس -->
        <section class="tq-card tq-card--panel">
            <div class="tq-card__head">
                <h2 class="tq-card__title"><?php echo t('حجوزاتك في التأسيس'); ?></h2>
            </div>

            <?php if (empty($tq_bookings)): ?>
                <?php echo tq_s_empty(
                    'calendar', 'peach',
                    t('لا حجوزات بعد'),
                    t('كل حجز تطلبه في التأسيس يظهر هنا بموعده ومعلمه وحالته: بانتظار التأكيد، أو بانتظار الدفع، أو مؤكد.'),
                    '', '', true
                ); ?>
            <?php else: ?>
                <ul class="tq-s-list">
                    <?php foreach ($tq_bookings as $b): ?>
                        <?php $badge = $tq_m->status_badge($b['status']); ?>
                        <li class="tq-s-item tq-s-item--stack">
                            <div class="tq-row" style="gap:var(--tq-space-m);inline-size:100%">
                                <img class="tq-avatar" src="<?php echo html_escape($tq_ses_photo($b['image'])); ?>"
                                     alt="<?php echo html_escape(t('صورة ') . $b['tutor']); ?>">
                                <span class="tq-s-item__body" style="flex:1;min-inline-size:0">
                                    <span class="tq-s-item__t tq-s-trunc"><?php echo html_escape($b['track_name']); ?></span>
                                    <span class="tq-s-item__s tq-s-trunc"><?php echo html_escape($b['tutor']); ?></span>
                                    <span class="tq-s-item__s"><?php echo tq_iso($b['when_text']); ?></span>
                                </span>
                                <?php echo tq_badge($badge[0], $badge[1]); ?>
                            </div>

                            <?php if ((int) $b['price'] > 0): ?>
                                <p class="tq-micro" style="margin:var(--tq-space-xs) 0 0">
                                    <?php echo $tq_sar($b['price']); ?>
                                    <?php if ($b['invoice_no'] !== ''): ?>
                                        <?php echo t('· فاتورة'); ?> <span class="tq-ltr"><?php echo html_escape($b['invoice_no']); ?></span>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>

                            <?php if ($b['needs_pay']): ?>
                                <div class="tq-pastel tq-pastel--peach" style="margin-block-start:var(--tq-space-s);padding:var(--tq-space-m)">
                                    <p class="tq-pastel__body" style="margin:0 0 var(--tq-space-s);font-size:.85rem">
                                        <?php echo t('أكد المعلم الموعد. ادفع'); ?>
                                        <strong><?php echo $tq_sar($b['invoice_total'] ?: $b['price']); ?></strong>
                                        <?php echo t('لتثبيته'); ?>
                                        <?php if ($b['pay_deadline']): ?>
                                            — <strong><?php echo tq_iso($tq_left($b['pay_deadline'])); ?></strong><?php echo t('، وبعدها يعود الموعد متاحا لغيرك.'); ?>
                                        <?php endif; ?>
                                    </p>

                                    <?php if ($tq_card): ?>
                                        <form method="post" action="<?php echo base_url('student/sessions/pay'); ?>" style="margin:0">
                                            <?php echo tq_csrf(); ?>
                                            <input type="hidden" name="back" value="foundation">
                                            <input type="hidden" name="session_id" value="<?php echo (int) $b['id']; ?>">
                                            <button class="tq-btn tq-btn--primary tq-btn--sm tq-btn--block" type="submit">
                                                <?php echo tq_icon('card', 16); ?> <?php echo t('ادفع الآن بالبطاقة'); ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <p class="tq-micro" style="margin:0">
                                            <?php echo t('الدفع بالبطاقة غير متاح الآن. حول المبلغ بنكيا بمرجع رقم الفاتورة،'); ?>
                                            <a href="<?php echo base_url('student/payments'); ?>"><?php echo t('وبيانات الحساب هنا'); ?></a>.
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($b['can_join']): ?>
                                <a class="tq-btn tq-btn--mastery tq-btn--sm tq-btn--block"
                                   href="<?php echo html_escape($b['meet_url']); ?>"
                                   target="_blank" rel="noopener noreferrer"
                                   style="margin-block-start:var(--tq-space-s)">
                                    <?php echo tq_icon('video', 16); ?>
                                    <?php echo $b['status'] === 'live' ? t('ادخل الحصة الجارية') : t('ادخل الحصة'); ?>
                                    <span class="tq-sr"><?php echo t('— يفتح في نافذة جديدة'); ?></span>
                                </a>
                            <?php elseif ($b['note'] !== '' && !$b['needs_pay']): ?>
                                <p class="tq-micro" style="margin:var(--tq-space-xs) 0 0">
                                    <?php echo html_escape($b['note']); ?>
                                </p>
                            <?php endif; ?>

                            <?php if ($b['cancel_reason'] !== ''
                                      && in_array($b['status'], array('declined', 'expired', 'refunded'), true)): ?>
                                <p class="tq-micro" style="margin:var(--tq-space-xs) 0 0;color:var(--tq-text3)">
                                    <?php echo html_escape(t($b['cancel_reason'])); ?>
                                </p>
                            <?php endif; ?>

                            <?php if ($b['can_cancel']): ?>
                                <form method="post" action="<?php echo base_url('student/sessions/cancel'); ?>"
                                      class="tq-form-inline" style="margin-block-start:var(--tq-space-xs)"
                                      data-tq-confirm-title="<?php echo te('إلغاء هذا الحجز؟'); ?>"
                                      data-tq-confirm="<?php echo te('يعود الموعد متاحا لغيرك، ويصل معلمك أنك ألغيت.'); ?>"
                                      data-tq-confirm-note="<?php echo te('لم يخصم منك شيء بعد، فالإلغاء الآن بلا تكلفة.'); ?>"
                                      data-tq-confirm-ok="<?php echo te('ألغ الحجز'); ?>"
                                      data-tq-confirm-tone="danger">
                                    <?php echo tq_csrf(); ?>
                                    <input type="hidden" name="back" value="foundation">
                                    <input type="hidden" name="session_id" value="<?php echo (int) $b['id']; ?>">
                                    <button class="tq-btn tq-btn--ghost tq-btn--sm tq-btn--block" type="submit">
                                        <?php echo t('إلغاء الحجز'); ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <?php /* والباب الآخر يذكر: من فتح التأسيس وهو يريد شرح درس في
                 منهجه جاء إلى الشاشة الخطأ، وشاشة لا تدله تتركه يظن أن
                 المنصة لا تعطي ما يريد. */ ?>
        <section class="tq-card tq-card--panel">
            <div class="tq-card__head"><h2 class="tq-card__title"><?php echo t('تريد شرح درس من منهجك؟'); ?></h2></div>
            <p class="tq-body">
                <?php echo t('التأسيس يبدأ من الصفر ولا يتبع منهج صف. وإن كنت تريد شرحا لدرس في مادتك فموضعه «حصص بالطلب» — هناك ترى معلمي صفك ومواعيدهم.'); ?>
            </p>
            <a class="tq-btn tq-btn--secondary tq-btn--block" href="<?php echo base_url('student/on-demand'); ?>">
                <?php echo t('حصص بالطلب'); ?>
            </a>
        </section>

    </aside>
</div>

<?php include 'portal_close.php'; ?>
