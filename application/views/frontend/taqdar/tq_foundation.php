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

/* حجوزات هذا القسم وحدها — والترشيح في الاستعلام (TQ-BOOKINGS-KIND): كان
   يجلب أحدث ثلاثين من كل الأنواع ثم يرشح هنا، فتسقط حجوزات التأسيس خلف
   حصص المنهج. والقادم أولا بأقربه. */
$tq_bookings = $tq_m->bookings_for_student($tq_uid, 30, 'foundation');

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

/* ---- TQ-FND-PACK — الرصيد والباقات --------------------------------
   الرصيد يقرأ **مرة واحدة** لكل الشاشة: البطاقة تسأل عنه، والنموذج
   يسأل عنه، ولوح الشريط يسأل عنه — واستعلام لكل سؤال يجعل شاشة فيها
   ثلاثة مسارات وستة معلمين تسأل عشر مرات عن شيء واحد. */
$tq_credits = $tq_fnd->credits($tq_uid);
$tq_packs   = $tq_fnd->packs();

/** ما بقي لهذا الطالب في مسار بعينه — من الرصيد المقروء لا من استعلام. */
$tq_left_in = function ($track_id) use ($tq_credits) {
    $n = 0;
    foreach ($tq_credits as $c) if ((int) $c['track_id'] === (int) $track_id) $n += (int) $c['left'];
    return $n;
};

/** باقات مسار بعينه، المعروضة وحدها. */
$tq_packs_of = function ($track_id) use ($tq_fnd) {
    return $tq_fnd->packs_of_track((int) $track_id);
};

/* مجموع ما بقي في كل المسارات — وهو ما يقرر شكل اللوح: من له رصيد يقرأ
   «رصيدك»، ومن لا رصيد له يقرأ عرضا. ولوحان يعرضان معا يجعلان من اشترى
   للتو يقرأ اعلانا يدعوه الى الشراء. */
$tq_have = 0;
foreach ($tq_credits as $c) $tq_have += (int) $c['left'];

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

        <?php /* ---- TQ-FND-PACK — رصيدك أو عرض الباقات ------------------
                 لوح واحد لا لوحان: من له رصيد يقرأ «رصيدك»، ومن لا رصيد
                 له يقرأ العرض. ولوحان يعرضان معا يجعلان من اشترى قبل
                 دقيقة يقرأ إعلانا يدعوه إلى الشراء، ومن نفد رصيده يقرأ
                 «رصيدك: صفر» بلا باب يشتري منه.

                 وموضعه فوق المسارات: الرصيد يغير **معنى كل زر تحته** —
                 «اطلب هذا الموعد» بلا رصيد تعني فاتورة ومهلة، وبرصيد
                 تعني حجزا يثبت. ومن قرأ الأزرار قبل أن يعرف رصيده يقرأها
                 على غير معناها. */ ?>
        <?php if ($tq_credits): ?>
        <section class="tq-section">
            <div class="tq-sectionhead">
                <h2><?php echo t('رصيدك من الحصص'); ?></h2>
                <span class="tq-sectionhead__count"><?php echo TQ_LRI . (int) $tq_have . TQ_PDI; ?></span>
            </div>

            <div class="tq-s-grid5">
                <?php foreach ($tq_credits as $tq_c): ?>
                    <?php
                    $tq_done = (int) $tq_c['total'] > 0
                             ? (int) round((int) $tq_c['used'] * 100 / (int) $tq_c['total']) : 0;
                    ?>
                    <article class="tq-card tq-fndc<?php echo (int) $tq_c['left'] <= 0 ? ' is-empty' : ''; ?>">
                        <p class="tq-micro" style="margin:0"><?php echo html_escape($tq_c['track_name']); ?></p>
                        <?php /* الرقم الكبير هو الباقي، والسطر تحته يقول من كم.
                                 وصيغة العدد في العربية تتبع الرقم، فـ«٦ حصة» و«حصتين»
                                 لا تكتبان بقالب واحد — و`tqs_ar_count()` هي القاعدة
                                 الواحدة لذلك في هذه الشجرة. */ ?>
                        <p class="tq-fndc__n">
                            <b><?php echo (int) $tq_c['left']; ?></b>
                            <span><?php echo t('متبقية من'); ?>
                                <?php echo tqs_ar_count((int) $tq_c['total'], array(t('حصة'), t('حصتين'), t('حصص'), t('حصة'))); ?></span>
                        </p>

                        <?php /* شريط يقرأ ما تقوله الأرقام: من يقرأ «٢ من ٦»
                                 يحسب، ومن يرى الشريط يعرف في لمحة. */ ?>
                        <span class="tq-fndc__bar" aria-hidden="true">
                            <i style="inline-size:<?php echo (int) $tq_done; ?>%"></i>
                        </span>

                        <p class="tq-micro" style="margin:var(--tq-space-xs) 0 0">
                            <?php echo html_escape($tq_c['name']); ?>
                            <?php if ($tq_c['days_left'] !== null): ?>
                                <?php /* المدة تقال بالأيام الباقية لا بالتاريخ:
                                         «يتبقى ٩ أيام» تحرك، و«ينتهي ٢٠٢٦-٠١-٠٣»
                                         تقرأ ولا تقرر. */ ?>
                                · <?php echo (int) $tq_c['days_left'] > 0
                                    ? t('يتبقى ') . (int) $tq_c['days_left'] . t(' يوما')
                                    : t('ينتهي اليوم'); ?>
                            <?php else: ?>
                                · <?php echo t('لا تنتهي'); ?>
                            <?php endif; ?>
                        </p>

                        <?php if ((int) $tq_c['left'] > 0): ?>
                            <a class="tq-btn tq-btn--ghost tq-btn--sm" style="margin-block-start:var(--tq-space-s)"
                               href="<?php echo base_url('student/foundation?track=' . (int) $tq_c['track_id']); ?>#tq-tutors">
                                <?php echo t('احجز حصة من رصيدك'); ?>
                            </a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>

            <p class="tq-caption" style="margin-block-start:var(--tq-space-m)">
                <?php echo t('الرصيد يخصم عند طلب الموعد، ويعود إليك كاملا إن اعتذر المعلم أو ألغيت قبل أن يفتح رابط الحصة. ولا تدفع شيئا عند الحجز — باقتك مدفوعة.'); ?>
            </p>
        </section>
        <?php endif; ?>

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
                        /* TQ-FOUNDATION-EMPTY — مسار بلا معلم مسند لا يعرض بسعر
                           كأنه يحجز: كان يقرأ «مجانية» وزر اختيار، ثم «لا معلم
                           متاح الآن» في كل زيارة. فيقال على بطاقته قبل النقرة. */
                        $tq_staffed = !empty($tq_t['teacher_ids']);
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
                                <?php if (!$tq_staffed): ?>
                                    <?php echo t('لم يسند إليه معلم بعد'); ?>
                                <?php else: ?>
                                    <?php echo (int) $tq_p['price'] > 0
                                        ? $tq_sar($tq_p['price']) . t(' للحصة')
                                        : t('مجانية'); ?>
                                <?php endif; ?>
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

        <?php
        /* ---- TQ-FND-PACK — الباقات المعروضة على الطالب ----------------
           باقات المسار المختار وحده متى اختار، وكلها متى لم يختر — وهو
           ترشيح الشاشة نفسه الذي يرشح المعلمين والمواعيد. وباقة مسار لا
           يتصفحه الطالب تقرأ ضجيجا، وإخفاء الكل حين لا يختار يجعل من
           فتح الشاشة عاريا لا يعرف أن للمنصة باقات أصلا. */
        $tq_show_packs = array();
        if ($f_track > 0) {
            $tq_show_packs = $tq_packs_of($f_track);
        } else {
            foreach ($tq_tracks as $tq_tid2 => $tq_t2) {
                foreach ($tq_packs_of((int) $tq_tid2) as $tq_pid2 => $tq_po2) {
                    $tq_show_packs[$tq_pid2] = $tq_po2;
                }
            }
        }
        ?>
        <?php if ($tq_show_packs): ?>
        <section class="tq-section" id="tq-packs">
            <div class="tq-sectionhead">
                <h2><?php echo $tq_have > 0 ? t('زد رصيدك بباقة') : t('باقات الحصص'); ?></h2>
            </div>

            <p class="tq-caption" style="margin-block-end:var(--tq-space-l)">
                <?php echo t('تدفع مرة واحدة، فيصير لك رصيد حصص تحجزها متى شئت مع أي معلم في المسار — بلا فاتورة لكل حصة ولا مهلة دفع.'); ?>
            </p>

            <div class="tq-s-grid5">
                <?php foreach ($tq_show_packs as $tq_pid => $tq_pk): ?>
                    <article class="tq-card tq-fndp<?php echo !empty($tq_pk['featured']) ? ' is-hot' : ''; ?>">
                        <?php if (!empty($tq_pk['featured'])): ?>
                            <span class="tq-fndp__flag"><?php echo t('الأكثر طلبا'); ?></span>
                        <?php endif; ?>

                        <p class="tq-micro" style="margin:0">
                            <?php echo html_escape($tq_pk['track'] ? $tq_pk['track']['name'] : t('التأسيس')); ?>
                        </p>
                        <p class="tq-fndp__n">
                            <b><?php echo (int) $tq_pk['sessions']; ?></b>
                            <span><?php echo t('حصة'); ?></span>
                        </p>
                        <p class="tq-fndp__price">
                            <b><?php echo $tq_sar((int) $tq_pk['price']); ?></b>
                            <?php if ((int) $tq_pk['save'] > 0): ?>
                                <?php /* المشطوب **مشتق** من سعر الحصة المفردة في
                                         المسار، فلا رقمان لحقيقة واحدة يفترقان. */ ?>
                                <del><?php echo $tq_sar((int) $tq_pk['list']); ?></del>
                                <span class="tq-fndp__save"><?php echo t('وفر ____٪', array(tq_iso((string) (int) $tq_pk['save_pct']))); ?></span>
                            <?php endif; ?>
                        </p>
                        <p class="tq-micro" style="margin:var(--tq-space-xs) 0 0">
                            <?php echo t('الحصة فيها'); ?> <?php echo $tq_sar((int) $tq_pk['unit']); ?>
                            <?php if ((int) $tq_pk['days'] > 0): ?>
                                · <?php echo t('صالحة'); ?> <?php echo (int) $tq_pk['days']; ?> <?php echo t('يوما'); ?>
                            <?php endif; ?>
                        </p>

                        <a class="tq-btn tq-btn--mastery tq-btn--sm" style="margin-block-start:var(--tq-space-s)"
                           href="<?php echo base_url('foundation-checkout/' . (int) $tq_pid); ?>">
                            <?php echo t('اشتر الباقة'); ?>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

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
                    <?php if ($f_track > 0 && empty($tq_tracks[$f_track]['teacher_ids'])): ?>
                        <?php /* «لا معلم فتح وقتا» غير «لا معلم أسند إليه أصلا»: الأول ينتظر
                                 أياما، والثاني لا يتغير حتى تسند الإدارة معلما. */ ?>
                        <?php echo tq_s_empty(
                            'users', 'mint',
                            t('لم يسند معلم إلى هذا المسار بعد'),
                            t('المسار معلن ولم تسند إليه الإدارة معلما حتى الآن، فلا مواعيد فيه. اعرض المسارات الأخرى، أو راسل الدعم ليبلغوك حين يفتح.'),
                            t('اعرض كل المسارات'),
                            base_url('student/foundation') . '#tq-tutors'
                        ); ?>
                    <?php elseif ($f_track > 0): ?>
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
                                    <?php
                                    /* TQ-FOUNDATION-PRICE — وسعر كل موعد سعر مساره: معلم يدرس
                                       مسارين بسعرين كانت بطاقته تعرض سعر أولهما، فيحجز الطالب
                                       الثاني على رقم غير رقمه. فحين تعرض المسارات كلها يكتب
                                       السعر مع المسار في الخيار نفسه. */
                                    $tq_opt = $sl['when_text'];
                                    if ($f_track <= 0 && (string) $sl['track_name'] !== '') {
                                        $tq_sp = $tq_fnd->pricing_for((int) $sl['track_id'], (int) $t['id']);
                                        $tq_opt .= ' — ' . $sl['track_name'] . ' · '
                                                 . ((int) $tq_sp['price'] > 0
                                                     ? number_format(((int) $tq_sp['price']) / 100, 2) . t(' ر.س')
                                                     : t('مجانية'));
                                    }
                                    ?>
                                    <option value="<?php echo (int) $sl['id']; ?>"><?php echo html_escape($tq_opt); ?></option>
                                <?php endforeach; ?>
                            </select>

                            <?php
                            /* TQ-FND-PACK — والزر يقول ماذا يقع حين يضغط.
                               الحجز من الرصيد لا فاتورة له ولا مهلة: يؤكد
                               المعلم فتثبت الحصة في الحال. وزر واحد يقول
                               «ثم تدفع ١٢٠ خلال ١٢ ساعة» لمن دفع بالفعل
                               يجعله يظن أن عليه دفعا ثانيا، فيتردد أو
                               يراسل الدعم.

                               والرصيد يقرأ لمسار **الموعد** لا للمختار:
                               الشاشة العارية تعرض معلما يدرس مسارين،
                               ورصيد أحدهما لا يحجز في الآخر. فحين لا يختار
                               الطالب مسارا لا يعد الزر بشيء — الخادم يقرر،
                               والرسالة بعد الحجز تقول ما وقع. */
                            $tq_my = $f_track > 0 ? $tq_left_in($f_track) : 0;
                            ?>
                            <button class="tq-btn tq-btn--mastery tq-btn--sm" type="submit">
                                <?php echo $tq_my > 0 ? t('احجز من رصيدك') : t('اطلب هذا الموعد'); ?>
                            </button>

                            <?php if ($tq_my > 0): ?>
                                <span class="tq-micro" style="flex-basis:100%">
                                    <?php echo t('تخصم حصة من رصيدك (يتبقى ____ بعدها)، ولا تدفع شيئا. يؤكد المعلم فتثبت الحصة في الحال، وإن اعتذر عادت الحصة إلى رصيدك.',
                                        array(tq_iso((string) max(0, $tq_my - 1)))); ?>
                                </span>
                            <?php elseif ((int) $t['pricing']['price'] > 0): ?>
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
                /* TQ-FND-PACK — والخطوة الرابعة تختلف بالرصيد: من دفع
                   باقته لا يدفع مرة ثانية، وخطوة تقول له «ادفع خلال ١٢
                   ساعة» تجعله ينتظر فاتورة لا تصدر ثم يظن حجزه ضاع. */
                $steps[] = $tq_have > 0
                    ? array(t('لا دفع — رصيدك يغطيها'),
                            t('باقتك مدفوعة، فتثبت الحصة فور تأكيد المعلم. وإن اعتذر عادت الحصة إلى رصيدك.'))
                    : array(t('ادفع لتثبيت الموعد'),
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

                            <?php /* TQ-FND-PACK — حصة الباقة **لا ثمن مستحق لها**:
                                     رقم بجوارها يقرأ مطالبة، ومن قرأه بحث عن زر
                                     دفع لا وجود له. فتقول «مدفوعة ضمن باقتك»
                                     وحدها — والمبلغ سجل في فاتورة الباقة لا
                                     في هذا السطر. */ ?>
                            <?php if (!empty($b['is_pack'])): ?>
                                <p class="tq-micro" style="margin:var(--tq-space-xs) 0 0">
                                    <?php echo tq_icon('check', 14); ?>
                                    <?php echo t('مدفوعة ضمن باقتك — لا فاتورة لهذه الحصة.'); ?>
                                </p>
                            <?php elseif ((int) $b['price'] > 0): ?>
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
                                      <?php /* TQ-FND-PACK — والملاحظة تقول ما يعود:
                                               «لم يخصم منك شيء» كاذبة على حصة باقة
                                               (خصمت حصة من رصيده)، والصواب أن تعود
                                               إليه — وهو ما يطمئنه قبل أن يضغط. */ ?>
                                      data-tq-confirm-note="<?php echo !empty($b['is_pack'])
                                          ? te('تعود الحصة إلى رصيد باقتك كاملة، فتحجز بها موعدا آخر.')
                                          : te('لم يخصم منك شيء بعد، فالإلغاء الآن بلا تكلفة.'); ?>"
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
