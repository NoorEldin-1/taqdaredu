<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * بوابة المعلم — الحصص.
 *
 * القاعدة الحاكمة لبوابة المعلم كلها:
 * المعلم مسند إلى مادة وصف بعينهما، وما لم يسند إليه لا يظهر في لوحته
 * أصلا: لا محتواه ولا طلابه ولا تقاريره. والنطاق يفرض في طبقة الاستعلام
 * لا في الواجهة — إخفاء زر في الواجهة ليس صلاحية. وشرط `teacher_id = <المعلم
 * الحالي>` مكتوب في كل استعلام وكل تحديث في `Taqdar_sessions_model`، لا هنا.
 *
 * الجدولان: `availability_slots` (أوقات المعلم) و`tutoring_sessions` (الطلبات).
 * وكان هذا الملف ينتظر اسمين لا وجود لهما — `teacher_availability` و
 * `session_requests` — فبقيت الشاشة فارغة والجدولان أمامها. التسمية الآن
 * تتبع القاعدة، والشبكة تحفظ فعلا.
 *
 * والشبكة أسبوعية بينما العمود موعد بعينه: مفتاح «اليوم:الفترة» يترجم في
 * النموذج إلى أقرب وقوع قادم، فلا يحفظ للمعلم موعد في الماضي.
 *
 * الأسبوع يبدأ الأحد — السوق سعودي.
 */

$tq_nav   = 'sessions';
$tq_role  = 'teacher';
$tq_title = t('الحصص');
$tq_sub   = t('طلبات الحجز وأوقاتك المتاحة');
$tq_icon  = 'video';

$tq_uid = (int) $this->session->userdata('user_id');

/* النموذج يحمل عبر get_instance(): العارض في CI3 ينسخ خصائص المتحكم إلى
   المحمل مرة واحدة قبل التصيير، فما حمل بعد بدء التصيير لا يظهر في `$this`. */
$tq_CI = get_instance();
$tq_CI->load->model('taqdar_sessions_model');
$tq_m = $tq_CI->taqdar_sessions_model;

/* ---- العرض يعرض ولا يكتب ---------------------------------------------
   كانت هذه الشاشة تعالج POST بنفسها وترسل نماذجها إلى مسار العرض
   `teacher/sessions`: كتابة في العرض تلتف على `write_guard`، بينما
   `Taqdar::sessions_save()` والمسار `teacher/sessions/save` موجودان
   ولا يرسل إليهما أحد — تنفيذان لعمل واحد، أحدهما ميت.
   والكتابة الآن في `Taqdar::sessions_save()` و`Taqdar::sessions_decide()`،
   ومنهما يخرج الإشعار إلى الطالب أيضا. */

/* ---- القراءة ---------------------------------------------------------- */
$tq_requests  = $tq_m->requests_for_teacher($tq_uid, ['requested']);
$tq_unpaid    = $tq_m->requests_for_teacher($tq_uid, ['awaiting_payment']);
$tq_confirmed = $tq_m->requests_for_teacher($tq_uid, ['confirmed', 'live']);
$tq_done      = $tq_m->requests_for_teacher($tq_uid, ['completed'], 10);

$tq_saved_slots = $tq_m->week_keys($tq_uid);
$tq_days        = $tq_m->days();
$tq_periods     = $tq_m->periods();

$tq_cfg     = $tq_m->config();
$tq_pricing = $tq_m->pricing_for($tq_uid);
$tq_sum     = $tq_m->teacher_summary($tq_uid);
$tq_paid    = (int) $tq_pricing['price'] > 0;

$tq_ses_photo = function ($image) {
    return tqs_person_img($image);
};

/** ثمن بالهللات إلى ريال — القسمة على مئة في موضع واحد. */
$tq_sar = function ($halalas) {
    return TQ_LRI . number_format(((int) $halalas) / 100, 2) . TQ_PDI . t('ر.س');
};

include 'portal_open.php';
?>

<?php if ($tq_flash = $this->session->flashdata('flash_message')): ?>
    <div class="tq-pastel tq-pastel--mint" style="margin-block-end:var(--tq-space-l)">
        <p class="tq-pastel__body" style="margin:0"><?php echo tq_iso($tq_flash); ?></p>
    </div>
<?php endif; ?>
<?php if ($tq_err = $this->session->flashdata('error_message')): ?>
    <div class="tq-pastel tq-pastel--rose" style="margin-block-end:var(--tq-space-l)">
        <p class="tq-pastel__body" style="margin:0"><?php echo tq_iso($tq_err); ?></p>
    </div>
<?php endif; ?>

<div class="tq-cols">
    <div>

        <!-- طلبات الحجز -->
        <section class="tq-section" aria-labelledby="tq-req-h">
            <div class="tq-sectionhead">
                <h2 id="tq-req-h"><?php echo t('طلبات الحجز'); ?></h2>
                <?php if ($tq_requests): ?>
                    <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_requests) . TQ_PDI; ?></span>
                <?php endif; ?>
            </div>

            <?php if ($tq_requests): ?>
                <div class="tq-card">
                    <ul class="tq-stack">
                        <?php foreach ($tq_requests as $tq_r): ?>
                            <li style="padding-block:var(--tq-space-m);border-block-end:1px solid var(--tq-line)">
                                <div class="tq-row" style="gap:var(--tq-space-l)">
                                    <img class="tq-avatar" src="<?php echo html_escape($tq_ses_photo($tq_r['image'])); ?>"
                                         alt="<?php echo html_escape(t('صورة') . $tq_r['student_name']); ?>">
                                    <div style="flex:1;min-inline-size:0">
                                        <p class="tq-strong" style="margin:0;color:var(--tq-navy)"><?php echo html_escape($tq_r['student_name']); ?></p>
                                        <p class="tq-micro" style="margin:0"><?php echo tq_iso($tq_r['when_text']); ?></p>
                                        <?php if ($tq_r['price'] > 0): ?>
                                            <?php /* ثمن هذا الطلب بعينه لا ثمن اليوم: السعر يجمد على
                                                     الحصة وقت طلبها، فتعديل الإدارة للتسعيرة بعده لا
                                                     يغير ما يقيد لك عنه. */ ?>
                                            <p class="tq-micro" style="margin:0">
                                                <?php echo $tq_sar($tq_r['price']); ?> · نصيبك
                                                <strong><?php echo $tq_sar($tq_r['share']); ?></strong>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <?php /* الاعتذار يلغي حصة طالب انتظرها: حوار التأكيد الموحد
                                             في البوابات لا ضغطة واحدة. */ ?>
                                    <form method="post" action="<?php echo base_url('teacher/sessions/decide'); ?>" class="tq-form-inline"
                                          data-tq-confirm-title="الاعتذار عن حصة <?php echo html_escape($tq_r['student_name']); ?>؟"
                                          data-tq-confirm="<?php echo te('يصله أنك اعتذرت، ويعود الموعد متاحا لغيره.'); ?>"
                                          data-tq-confirm-note="الاعتذار المبكر أفضل للطالب من انتظار لا ينتهي. ولك أن تفتح الفترة نفسها من جديد."
                                          data-tq-confirm-ok="أعتذر عن الطلب"
                                          data-tq-confirm-tone="danger">
                                        <?php echo tq_csrf(); ?>
                                        <input type="hidden" name="decision" value="decline">
                                        <input type="hidden" name="session_id" value="<?php echo (int) $tq_r['id']; ?>">
                                        <button class="tq-btn tq-btn--secondary tq-btn--sm" type="submit"><?php echo t('اعتذار'); ?></button>
                                    </form>
                                </div>

                                <?php /* التأكيد ورابط اللقاء نموذج واحد: «مؤكد» بلا رابط يقول
                                         للطالب إن الحصة قائمة ولا يقول أين يدخلها — فيقف في
                                         موعده أمام شاشة بلا باب. والنموذج يرفض بلا رابط صحيح. */ ?>
                                <form method="post" action="<?php echo base_url('teacher/sessions/decide'); ?>"
                                      class="tq-row" style="gap:var(--tq-space-s);flex-wrap:wrap;margin-block-start:var(--tq-space-m)">
                                    <?php echo tq_csrf(); ?>
                                    <input type="hidden" name="decision" value="confirm">
                                    <input type="hidden" name="session_id" value="<?php echo (int) $tq_r['id']; ?>">
                                    <label class="tq-sr" for="tq-meet-<?php echo (int) $tq_r['id']; ?>">
                                        رابط لقاء الحصة مع <?php echo html_escape($tq_r['student_name']); ?>
                                    </label>
                                    <input class="tq-input" id="tq-meet-<?php echo (int) $tq_r['id']; ?>"
                                           name="meet_url" type="url" dir="ltr" required
                                           style="flex:1;min-inline-size:18rem"
                                           placeholder="https://meet.google.com/abc-defg-hij"
                                           aria-describedby="tq-meet-h-<?php echo (int) $tq_r['id']; ?>">
                                    <button class="tq-btn tq-btn--mastery tq-btn--sm" type="submit"><?php echo t('تأكيد وإرسال الرابط'); ?></button>
                                    <span class="tq-micro" id="tq-meet-h-<?php echo (int) $tq_r['id']; ?>" style="flex-basis:100%">
                                        <?php echo html_escape(t('رابط') . $tq_m->meet_hosts_text() . ' — '); ?>
                                        <?php if ($tq_r['price'] > 0): ?>
                                            <?php /* التأكيد لا يثبت الموعد إن كان بثمن. وقولها هنا
                                                     يمنع أن يعتمد المعلم على وقت لم يشتر بعد. */ ?>
                                            يصل الطالب فاتورة الحصة، ويظهر له الرابط بعد أن يدفع.
                                            والموعد لا يثبت لك حتى ذلك الحين.
                                        <?php else: ?>
                                            يظهر للطالب في شاشته بعد التأكيد.
                                        <?php endif; ?>
                                    </span>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <div class="tq-card tq-empty">
                    <span class="tq-icon-box tq-pastel--lilac" style="color:var(--tq-lilac-ink)" aria-hidden="true"><?php echo tq_icon('calendar', 24); ?></span>
                    <h3 class="tq-empty__title"><?php echo t('لا طلبات حجز الآن'); ?></h3>
                    <p class="tq-empty__text">
                        <?php echo tq_iso(t('حين يطلب أحد طلابك حصة خاصة، يظهر طلبه هنا بموعده')
                            . ($tq_paid ? t('وثمنه ونصيبك منه') : '')
                            . t('، فتؤكده أو تعتذر عنه خلال') . $tq_cfg['pay_hours']
                            . t('ساعة. حدد أوقاتك المتاحة أدناه ليعرف الطالب متى يطلب.')); ?>
                    </p>
                    <a class="tq-btn tq-btn--secondary" href="<?php echo base_url('teacher'); ?>"><?php echo t('عودة إلى اللوحة'); ?></a>
                </div>
            <?php endif; ?>
        </section>

        <?php /* ── بانتظار دفع الطالب ─────────────────────────────────────
                 محطة قائمة بذاتها لا حاشية على «مؤكدة»: الموعد ليس محجوزا
                 بعد، والمعلم يجب أن يعرف أن وقته ما زال معروضا. وإخفاء هذه
                 القائمة يجعله يقرأ «أكدت» ويظن الموعد له، ثم يجده مفتوحا
                 لغيره حين تمضي المهلة. */ ?>
        <?php if ($tq_unpaid): ?>
            <section class="tq-section" aria-labelledby="tq-wait-h">
                <div class="tq-sectionhead">
                    <h2 id="tq-wait-h"><?php echo t('بانتظار دفع الطالب'); ?></h2>
                    <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_unpaid) . TQ_PDI; ?></span>
                </div>
                <div class="tq-card">
                    <p class="tq-caption" style="margin-block-start:0">
                        <?php echo t('أكدت هذه المواعيد وصدرت فواتيرها.'); ?> <strong><?php echo t('ولا تثبت حتى يدفع الطالب'); ?></strong> <?php echo t('— وحتى ذلك الحين يبقى الموعد معروضا، فإن مضت المهلة عاد متاحا لغيره.'); ?>
                    </p>
                    <ul class="tq-stack">
                        <?php foreach ($tq_unpaid as $tq_c): ?>
                            <?php $tq_b = $tq_m->status_badge($tq_c['status']); ?>
                            <li class="tq-row" style="gap:var(--tq-space-l);padding-block:var(--tq-space-m);border-block-end:1px solid var(--tq-line)">
                                <img class="tq-avatar" src="<?php echo html_escape($tq_ses_photo($tq_c['image'])); ?>"
                                     alt="<?php echo html_escape(t('صورة') . $tq_c['student_name']); ?>">
                                <div style="flex:1;min-inline-size:0">
                                    <p class="tq-strong" style="margin:0;color:var(--tq-navy)"><?php echo html_escape($tq_c['student_name']); ?></p>
                                    <p class="tq-micro" style="margin:0"><?php echo tq_iso($tq_c['when_text']); ?></p>
                                    <?php if ($tq_c['pay_deadline']): ?>
                                        <p class="tq-micro" style="margin:0">
                                            مهلة الدفع حتى
                                            <?php echo tq_iso(date('Y-m-d H:i', strtotime($tq_c['pay_deadline']))); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <?php if ($tq_c['price'] > 0): ?>
                                    <span class="tq-micro" style="text-align:center">
                                        <strong style="display:block;color:var(--tq-navy)"><?php echo $tq_sar($tq_c['share']); ?></strong>
                                        <?php echo t('نصيبك'); ?>
                                    </span>
                                <?php endif; ?>
                                <?php echo tq_badge($tq_b[0], $tq_b[1]); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>
        <?php endif; ?>

        <!-- حصص مؤكدة: أثر التأكيد يرى، فلا يختفي الطلب بلا مآل -->
        <?php if ($tq_confirmed): ?>
            <section class="tq-section" aria-labelledby="tq-ok-h">
                <div class="tq-sectionhead">
                    <h2 id="tq-ok-h"><?php echo $tq_paid ? t('حصص مؤكدة ومدفوعة') : t('حصص مؤكدة'); ?></h2>
                    <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_confirmed) . TQ_PDI; ?></span>
                </div>
                <div class="tq-card">
                    <ul class="tq-stack">
                        <?php foreach ($tq_confirmed as $tq_c): ?>
                            <?php $tq_b = $tq_m->status_badge($tq_c['status']); ?>
                            <li style="padding-block:var(--tq-space-m);border-block-end:1px solid var(--tq-line)">
                                <div class="tq-row" style="gap:var(--tq-space-l)">
                                    <img class="tq-avatar" src="<?php echo html_escape($tq_ses_photo($tq_c['image'])); ?>"
                                         alt="<?php echo html_escape(t('صورة') . $tq_c['student_name']); ?>">
                                    <div style="flex:1;min-inline-size:0">
                                        <p class="tq-strong" style="margin:0;color:var(--tq-navy)"><?php echo html_escape($tq_c['student_name']); ?></p>
                                        <p class="tq-micro" style="margin:0"><?php echo tq_iso($tq_c['when_text']); ?></p>
                                        <?php if ($tq_c['meet_url'] !== ''): ?>
                                            <p class="tq-micro tq-s-trunc" style="margin:0" dir="ltr">
                                                <?php echo html_escape($tq_c['meet_url']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($tq_c['price'] > 0): ?>
                                        <span class="tq-micro" style="text-align:center">
                                            <strong style="display:block;color:var(--tq-navy)"><?php echo $tq_sar($tq_c['share']); ?></strong>
                                            <?php echo t('نصيبك'); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($tq_c['meet_url'] !== '' && !$tq_c['is_over']): ?>
                                        <a class="tq-btn tq-btn--mastery tq-btn--sm" target="_blank" rel="noopener"
                                           href="<?php echo html_escape($tq_c['meet_url']); ?>">
                                            <?php echo tq_icon('video', 16); ?> ادخل الحصة
                                        </a>
                                    <?php endif; ?>
                                    <?php echo tq_badge($tq_b[0], $tq_b[1]); ?>
                                </div>

                                <?php /* «انتهت الحصة» — وهي اللحظة التي يقيد فيها نصيبه
                                         ويموت فيها الرابط. ولا تظهر قبل موعدها: زر ينهي
                                         حصة الغد يجعل «انتهت» تعني «ضغطت الزر»، ويقيد
                                         مالا مقابل وقت لم يعط بعد. */ ?>
                                <?php if ($tq_c['can_complete']): ?>
                                    <form method="post" action="<?php echo base_url('teacher/sessions/complete'); ?>"
                                          class="tq-form-inline" style="margin-block-start:var(--tq-space-m)"
                                          data-tq-confirm-title="أنهيت الحصة مع <?php echo html_escape($tq_c['student_name']); ?>؟"
                                          data-tq-confirm="<?php echo $tq_c['price'] > 0
                                              ? t('يغلق رابط اللقاء، ويقيد نصيبك في محفظتك.')
                                              : t('يغلق رابط اللقاء، ولا يستطيع الطالب الدخول بعده.'); ?>"
                                          data-tq-confirm-note="لا تضغط قبل أن تنتهي فعلا — الرابط لا يفتح مرة أخرى."
                                          data-tq-confirm-ok="نعم، انتهت">
                                        <?php echo tq_csrf(); ?>
                                        <input type="hidden" name="session_id" value="<?php echo (int) $tq_c['id']; ?>">
                                        <button class="tq-btn tq-btn--secondary tq-btn--sm" type="submit">
                                            <?php echo tq_icon('check', 16); ?> أعلن انتهاء الحصة
                                            <?php if ($tq_c['price'] > 0): ?>
                                                <span class="tq-sr"><?php echo t('— ويقيد نصيبك'); ?></span>
                                            <?php endif; ?>
                                        </button>
                                    </form>
                                <?php elseif ($tq_c['note'] !== ''): ?>
                                    <p class="tq-micro" style="margin:var(--tq-space-xs) 0 0">
                                        <?php echo html_escape($tq_c['note']); ?>
                                    </p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>
        <?php endif; ?>

        <?php /* ── حصص انتهت ───────────────────────────────────────────────
                 «كم قبضت عن حصصي» سؤال يطرح بعد الانتهاء لا قبله، ولم يكن
                 في الشاشة موضع يجيبه — تختفي الحصة من «مؤكدة» ولا تظهر في
                 مكان، فيسأل المعلم أين ذهبت. و«قيد» هنا تعني أن المال دخل
                 دفتره فعلا، فما لم يقيد يظهر بلا وسم لا بوسم كاذب. */ ?>
        <?php if ($tq_done): ?>
            <section class="tq-section" aria-labelledby="tq-done-h">
                <div class="tq-sectionhead">
                    <h2 id="tq-done-h"><?php echo t('حصص انتهت'); ?></h2>
                    <?php if ($tq_paid): ?>
                        <a class="tq-caption" href="<?php echo base_url('teacher/wallet'); ?>"><?php echo t('المحفظة'); ?></a>
                    <?php endif; ?>
                </div>
                <div class="tq-card">
                    <ul class="tq-stack">
                        <?php foreach ($tq_done as $tq_c): ?>
                            <li class="tq-row" style="gap:var(--tq-space-l);padding-block:var(--tq-space-m);border-block-end:1px solid var(--tq-line)">
                                <div style="flex:1;min-inline-size:0">
                                    <p class="tq-strong" style="margin:0;color:var(--tq-navy)"><?php echo html_escape($tq_c['student_name']); ?></p>
                                    <p class="tq-micro" style="margin:0"><?php echo tq_iso($tq_c['when_text']); ?></p>
                                </div>
                                <?php if ($tq_c['price'] > 0): ?>
                                    <span class="tq-micro" style="text-align:center">
                                        <strong style="display:block;color:var(--tq-navy)"><?php echo $tq_sar($tq_c['share']); ?></strong>
                                        <?php echo $tq_c['credited_at'] ? t('في محفظتك') : t('لم يقيد بعد'); ?>
                                    </span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>
        <?php endif; ?>

        <!-- الأوقات المتاحة: الأسبوع يبدأ الأحد -->
        <form class="tq-card tq-card--panel" method="post"
              action="<?php echo base_url('teacher/sessions/save'); ?>">
            <?php echo tq_csrf(); ?>
            <fieldset style="border:0;padding:0;margin:0">
                <legend class="tq-h2" style="padding:0"><?php echo t('أوقاتي المتاحة'); ?></legend>
                <p class="tq-caption">
                    <?php echo t('اختر الفترات التي تقبل فيها حصصا خاصة. الطالب لا يرى غيرها، فلا يصلك طلب في وقت لا تعمل فيه. والاختيار يسري على الأيام السبعة القادمة، وما حجز منه لا يلغى برفع العلامة — بل بالاعتذار عن طلبه.'); ?>
                </p>
                <?php /* الفترة إتاحة لا حصة: «مساء» خمس ساعات، وكان اختيارها
                         يولد موعدا واحدا يحجزه طالب فيقفلها كلها — فيرد أربعة
                         طلاب على وقت هو فارغ. وصارت تفرش إلى مواعيد بطول الحصة. */ ?>
                <p class="tq-caption">
                    <strong>كل فترة تفرش إلى مواعيد بطول
                    <?php echo tq_iso($tq_cfg['minutes'] . t('دقيقة')); ?></strong> —
                    فترة «مساء» مثلا تعطي
                    <?php echo tq_iso((string) max(1, intdiv(300, $tq_cfg['minutes'])) . t('مواعيد')); ?>
                    يحجزها طلاب مختلفون، لا موعدا واحدا يشغلها كلها.
                    ومدة الحصة تحددها الإدارة.
                </p>

                <div style="overflow-x:auto">
                    <table class="tq-table">
                        <caption class="tq-sr"><?php echo t('أوقاتك المتاحة في أيام الأسبوع'); ?></caption>
                        <thead>
                            <tr>
                                <th scope="col"><?php echo t('اليوم'); ?></th>
                                <?php foreach ($tq_periods as $tq_pk => $tq_p): ?>
                                    <th scope="col">
                                        <?php echo html_escape($tq_p['label']); ?>
                                        <span class="tq-micro" style="display:block"><?php echo tq_iso($tq_p['range']); ?></span>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tq_days as $tq_di => $tq_day): ?>
                                <tr>
                                    <th scope="row" style="text-align:start;padding:var(--tq-space-m) var(--tq-space-l)">
                                        <?php echo html_escape($tq_day); ?>
                                    </th>
                                    <?php foreach ($tq_periods as $tq_pk => $tq_p): ?>
                                        <?php $tq_id = 'slot-' . $tq_di . '-' . $tq_pk; ?>
                                        <td data-label="<?php echo html_escape($tq_day . ' ' . $tq_p['label']); ?>">
                                            <span class="tq-row" style="gap:var(--tq-space-s)">
                                                <input type="checkbox" id="<?php echo $tq_id; ?>"
                                                       name="slots[]" value="<?php echo $tq_di . ':' . $tq_pk; ?>"
                                                       <?php echo in_array($tq_di . ':' . $tq_pk, $tq_saved_slots, true) ? 'checked' : ''; ?>>
                                                <label class="tq-micro" for="<?php echo $tq_id; ?>">
                                                    <?php echo html_escape($tq_day . ' ' . $tq_p['label']); ?>
                                                </label>
                                            </span>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <button class="tq-btn tq-btn--primary" type="submit" style="margin-block-start:var(--tq-space-xl)"
                        aria-describedby="tq-slots-note">
                    <?php echo t('حفظ أوقاتي'); ?>
                </button>
                <p class="tq-field__msg tq-field__hint" id="tq-slots-note" style="margin-block-start:var(--tq-space-m)">
                    <?php echo t('الحفظ فوري: كل فترة تختارها تصير موعدا يراه الطالب في «حصص بالطلب».'); ?>
                </p>
            </fieldset>
        </form>
    </div>

    <aside class="tq-aside">

        <?php /* ثمن حصتك ونصيبك منها — أول ما يسأل عنه معلم، ولم يكن في
                 بوابته موضع واحد يقوله: يدرس ولا يعرف بكم بيع وقته ولا كم
                 يبقى له. والرقمان تحددهما الإدارة، والشاشة تعرضهما ولا
                 تحكم — فالمعلم يقرأ ما سيقيد له قبل أن يؤكد لا بعده. */ ?>
        <?php if ($tq_paid): ?>
            <div class="tq-pastel tq-pastel--mint">
                <span class="tq-pastel__label tq-micro"><?php echo t('ثمن حصتك'); ?></span>
                <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0;font-size:1.35rem;font-weight:700">
                    <?php echo $tq_sar($tq_pricing['price']); ?>
                </p>
                <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                    <?php echo t('نصيبك منها'); ?> <strong><?php echo $tq_sar($tq_pricing['share']); ?></strong>
                    (<?php echo tq_iso(rtrim(rtrim(number_format($tq_pricing['percent'], 2), '0'), '.') . t('٪')); ?>)،
                    والباقي <?php echo $tq_sar($tq_pricing['platform']); ?> عمولة المنصة.
                    ومدة الحصة <?php echo tq_iso($tq_cfg['minutes'] . t('دقيقة')); ?>.
                </p>
                <p class="tq-micro" style="margin:var(--tq-space-m) 0 0">
                    <?php echo $tq_pricing['from_teacher']
                        ? t('تسعيرة خاصة بك حددتها الإدارة.')
                        : t('التسعيرة العامة للمنصة، وتحددها الإدارة.'); ?>
                    ونصيبك يقيد في محفظتك <strong><?php echo t('حين تعلن انتهاء الحصة'); ?></strong> <?php echo t('لا حين يدفع الطالب.'); ?>
                </p>
            </div>
        <?php endif; ?>

        <div class="tq-pastel tq-pastel--sky">
            <span class="tq-pastel__label tq-micro"><?php echo t('قاعدة الحصص'); ?></span>
            <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                <?php echo tq_iso(t('طلب بلا رد') . $tq_cfg['pay_hours']
                    . t('ساعة يلغى تلقائيا ويعاد للطالب. الاعتذار المبكر أفضل للطالب من انتظار لا ينتهي.')); ?>
            </p>
            <?php if ($tq_paid): ?>
                <p class="tq-pastel__body" style="margin:var(--tq-space-m) 0 0">
                    <?php echo tq_iso(t('والموعد لا يثبت لك إلا بعد أن يدفع الطالب خلال')
                        . $tq_cfg['pay_hours'] . t('ساعة من تأكيدك — وحتى ذلك الحين يبقى معروضا لغيره.')); ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title"><?php echo t('حصصي بالأرقام'); ?></h2></div>
            <ul class="tq-stack">
                <li class="tq-row tq-row--between">
                    <span class="tq-caption"><?php echo t('طلبات تنتظر ردك'); ?></span>
                    <?php echo tq_num($tq_sum['pending']); ?>
                </li>
                <?php if ($tq_paid): ?>
                    <li class="tq-row tq-row--between">
                        <span class="tq-caption"><?php echo t('تنتظر دفع الطالب'); ?></span>
                        <?php echo tq_num($tq_sum['unpaid']); ?>
                    </li>
                <?php endif; ?>
                <li class="tq-row tq-row--between">
                    <span class="tq-caption"><?php echo t('حصص مثبتة قادمة'); ?></span>
                    <?php echo tq_num($tq_sum['booked']); ?>
                </li>
                <li class="tq-row tq-row--between">
                    <span class="tq-caption"><?php echo t('حصص انتهت'); ?></span>
                    <?php echo tq_num($tq_sum['done']); ?>
                </li>
                <li class="tq-row tq-row--between">
                    <span class="tq-caption"><?php echo t('مواعيد مفتوحة'); ?></span>
                    <?php echo tq_num($tq_sum['open']); ?>
                </li>
            </ul>

            <?php if ($tq_paid): ?>
                <?php /* «كسبته» و«ينتظر» رقمان مختلفان: الأول عن حصص انعقدت،
                         والثاني عن مثبتة لم تنعقد بعد. وجمعهما في رقم واحد
                         يعد المعلم بما لم يستحقه. */ ?>
                <ul class="tq-stack" style="margin-block-start:var(--tq-space-l);border-block-start:1px solid var(--tq-line);padding-block-start:var(--tq-space-l)">
                    <li class="tq-row tq-row--between">
                        <span class="tq-caption"><?php echo t('كسبته من حصص انتهت'); ?></span>
                        <strong style="color:var(--tq-navy)"><?php echo $tq_sar($tq_sum['earned']); ?></strong>
                    </li>
                    <li class="tq-row tq-row--between">
                        <span class="tq-caption"><?php echo t('ينتظر انعقاد حصصه'); ?></span>
                        <strong><?php echo $tq_sar($tq_sum['upcoming']); ?></strong>
                    </li>
                </ul>
                <a class="tq-btn tq-btn--ghost tq-btn--sm tq-btn--block"
                   href="<?php echo base_url('teacher/wallet'); ?>"
                   style="margin-block-start:var(--tq-space-m)">
                    <?php echo t('تفاصيل المحفظة'); ?>
                </a>
            <?php endif; ?>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
