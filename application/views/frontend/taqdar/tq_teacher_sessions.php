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
 * ── TQ-SESSION-GRID — الوقت ساعة يكتبها، والصف جزء منه ──────────────
 *
 * كانت الشاشة شبكة خانات: ثلاث فترات ثابتة في سبعة أيام، يعلم المعلم ما
 * يقبله. وهي تخطئ في اثنتين:
 *
 *   ١ — **الفترة ليست وقته.** «مساء» خمس ساعات من الرابعة إلى التاسعة،
 *       ومن يعمل من العاشرة صباحا إلى الثانية ظهرا لا يجد خانة تقول ذلك:
 *       يعلم «صباحا» و«ظهرا» فيفتح من الثامنة إلى الرابعة — ست ساعات لم
 *       يردها، ويصله طلب في وقت لا يعمل فيه.
 *   ٢ — **المعلم يدرس أكثر من صف.** ومن يعطي الثالث الابتدائي صباحا
 *       والرابع عصرا لا يستطيع أن يقول ذلك أصلا، فيصل الطلبان مختلطين
 *       ولا يعرف الطالب أي موعد له.
 *
 * فصارت الأوقات **سطورا**: يوم، ومن، وإلى، وصف. وكل سطر قاعدة أسبوعية
 * دائمة في `tq_teacher_windows`، تفرش مواعيد بطول الحصة في الأيام القادمة
 * ويتجدد فرشها كل ساعة — فلا تنفد الشبكة بعد أسبوع من آخر حفظ.
 *
 * والصف هو ما يجعل الطالب يرى **مواعيد صفه وحدها**.
 *
 * **وصفوف المعلم ومواده صفوف كورساته ومواده وحدها** — لا كل ما في
 * المنصة: الحصة شرح لمنهج صف بعينه في مادة بعينها، ومن يفتح وقتا لما لا
 * يدرسه يجلس ساعة مع طالب لا يفيده فيها وقد قبض ثمنها. فالمنتقيان لا
 * يعرضان إلا ما له فيه كورس، ومن لا كورس له لا يفتح وقتا أصلا ويقال له
 * لماذا وما الطريق.
 *
 * **والمادة تتبع الصف**: معلم له رياضيات الثالث ولغة عربية الرابع لا
 * تعرض له «رياضيات» حين يختار الرابع — والحكم في `teacher_scope()`
 * (أزواج صف/مادة)، والسكربت يضيق القائمة من الوصف نفسه الذي يفحص به
 * الخادم. فلا قاعدتان تفترقان.
 * (والمواعيد التي فتحت قبل هذا التغيير تحمل صفر — «كل الصفوف» — فتبقى
 * معروضة للجميع كما كانت، ولا يكتب صفر جديد.)
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

$tq_windows = $tq_m->windows_for($tq_uid);
$tq_days    = $tq_m->days();
$tq_scope   = $tq_m->teacher_scope($tq_uid);
$tq_grades  = $tq_scope['grades'];
$tq_subs    = $tq_scope['subjects'];

/* TQ-FOUNDATION — ومسارات التأسيس نطاق ثان بجوار الصف والمادة.
   والمعلم قد يملك أحدهما دون الآخر: معلم تأسيس بلا كورس واحد يفتح وقت
   تأسيس ولا يفتح وقت منهج، ومعلم منهج بلا إسناد تأسيس عكسه. فالجدول
   يعرض ما يملكه صاحبه، والخادم يفحص الاثنين. */
$tq_CI->load->model('taqdar_foundation_model');
$tq_tracks  = $tq_CI->taqdar_foundation_model->teacher_tracks($tq_uid);
$tq_has_cur = ($tq_grades && $tq_subs);
$tq_has_fnd = (bool) $tq_tracks;

$tq_cfg     = $tq_m->config();
$tq_pricing = $tq_m->pricing_for($tq_uid);
$tq_sum     = $tq_m->teacher_summary($tq_uid);
$tq_paid    = (int) $tq_pricing['price'] > 0;

$tq_ses_photo = function ($image) {
    return tqs_person_img($image);
};

/** ثمن بالهللات إلى ريال — القسمة على مئة في موضع واحد. */
$tq_sar = function ($halalas) {
    return TQ_LRI . number_format(((int) $halalas) / 100, 2) . TQ_PDI . t(' ر.س');
};

include 'portal_open.php';
?>

<?php if ($tq_flash = tq_flash('flash_message')): ?>
    <div class="tq-pastel tq-pastel--mint" style="margin-block-end:var(--tq-space-l)">
        <p class="tq-pastel__body" style="margin:0"><?php echo tq_iso($tq_flash); ?></p>
    </div>
<?php endif; ?>
<?php if ($tq_err = tq_flash('error_message')): ?>
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
                                         alt="<?php echo html_escape(t('صورة ') . $tq_r['student_name']); ?>">
                                    <div style="flex:1;min-inline-size:0">
                                        <p class="tq-strong" style="margin:0;color:var(--tq-navy)"><?php echo html_escape($tq_r['student_name']); ?></p>
                                        <p class="tq-micro" style="margin:0"><?php echo tq_iso($tq_r['when_text']); ?></p>
                                        <?php /* المادة مع الصف: المعلم يفتح لأكثر من مادة، وسطر بلا
                                                 مادة يترك من يقرأ طلبه لا يعرف في أي مادة يحضر. */ ?>
                                        <?php $tq_tag = array_filter(array((string) ($tq_r['subject_name'] ?? ''),
                                            (int) $tq_r['grade_id'] > 0 ? $tq_r['grade_name'] : '')); ?>
                                        <?php if ($tq_tag): ?>
                                            <p class="tq-micro" style="margin:0"><?php echo html_escape(implode(' · ', $tq_tag)); ?></p>
                                        <?php endif; ?>
                                        <?php if ($tq_r['price'] > 0): ?>
                                            <?php /* ثمن هذا الطلب بعينه لا ثمن اليوم: السعر يجمد على
                                                     الحصة وقت طلبها، فتعديل الإدارة للتسعيرة بعده لا
                                                     يغير ما يقيد لك عنه. */ ?>
                                            <p class="tq-micro" style="margin:0">
                                                <?php echo $tq_sar($tq_r['price']); ?> <?php echo t('· نصيبك'); ?>
                                                <strong><?php echo $tq_sar($tq_r['share']); ?></strong>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <?php /* الاعتذار يلغي حصة طالب انتظرها: حوار التأكيد الموحد
                                             في البوابات لا ضغطة واحدة. */ ?>
                                    <form method="post" action="<?php echo base_url('teacher/sessions/decide'); ?>" class="tq-form-inline"
                                          data-tq-confirm-title="<?php echo te('الاعتذار عن حصة ____؟', array(html_escape($tq_r['student_name']))); ?>"
                                          data-tq-confirm="<?php echo te('يصله أنك اعتذرت، ويعود الموعد متاحا لغيره.'); ?>"
                                          data-tq-confirm-note="<?php echo te('الاعتذار المبكر أفضل للطالب من انتظار لا ينتهي. ولك أن تفتح الفترة نفسها من جديد.'); ?>"
                                          data-tq-confirm-ok="<?php echo te('أعتذر عن الطلب'); ?>"
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
                                        <?php echo t('رابط لقاء الحصة مع'); ?> <?php echo html_escape($tq_r['student_name']); ?>
                                    </label>
                                    <input class="tq-input" id="tq-meet-<?php echo (int) $tq_r['id']; ?>"
                                           name="meet_url" type="url" dir="ltr" required
                                           style="flex:1;min-inline-size:18rem"
                                           placeholder="https://meet.google.com/abc-defg-hij"
                                           aria-describedby="tq-meet-h-<?php echo (int) $tq_r['id']; ?>">
                                    <button class="tq-btn tq-btn--mastery tq-btn--sm" type="submit"><?php echo t('تأكيد وإرسال الرابط'); ?></button>
                                    <span class="tq-micro" id="tq-meet-h-<?php echo (int) $tq_r['id']; ?>" style="flex-basis:100%">
                                        <?php echo html_escape(t('رابط ') . $tq_m->meet_hosts_text() . ' — '); ?>
                                        <?php if ($tq_r['price'] > 0): ?>
                                            <?php /* التأكيد لا يثبت الموعد إن كان بثمن. وقولها هنا
                                                     يمنع أن يعتمد المعلم على وقت لم يشتر بعد. */ ?>
                                            <?php echo t('يصل الطالب فاتورة الحصة، ويظهر له الرابط بعد أن يدفع. والموعد لا يثبت لك حتى ذلك الحين.'); ?>
                                        <?php else: ?>
                                            <?php echo t('يظهر للطالب في شاشته بعد التأكيد.'); ?>
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
                            . ($tq_paid ? t(' وثمنه ونصيبك منه') : '')
                            . t('، فتؤكده أو تعتذر عنه خلال ') . $tq_cfg['pay_hours']
                            . t(' ساعة. حدد أوقاتك المتاحة أدناه ليعرف الطالب متى يطلب.')); ?>
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
                                     alt="<?php echo html_escape(t('صورة ') . $tq_c['student_name']); ?>">
                                <div style="flex:1;min-inline-size:0">
                                    <p class="tq-strong" style="margin:0;color:var(--tq-navy)"><?php echo html_escape($tq_c['student_name']); ?></p>
                                    <p class="tq-micro" style="margin:0"><?php echo tq_iso($tq_c['when_text']); ?></p>
                                    <?php /* المادة مع الصف: المعلم يفتح لأكثر من مادة، وسطر بلا
                                             مادة يترك من يقرأ طلبه لا يعرف في أي مادة يحضر. */ ?>
                                    <?php $tq_tag = array_filter(array((string) ($tq_c['subject_name'] ?? ''),
                                        (int) $tq_c['grade_id'] > 0 ? $tq_c['grade_name'] : '')); ?>
                                    <?php if ($tq_tag): ?>
                                        <p class="tq-micro" style="margin:0"><?php echo html_escape(implode(' · ', $tq_tag)); ?></p>
                                    <?php endif; ?>
                                    <?php if ($tq_c['pay_deadline']): ?>
                                        <p class="tq-micro" style="margin:0">
                                            <?php echo t('مهلة الدفع حتى'); ?>
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
                                         alt="<?php echo html_escape(t('صورة ') . $tq_c['student_name']); ?>">
                                    <div style="flex:1;min-inline-size:0">
                                        <p class="tq-strong" style="margin:0;color:var(--tq-navy)"><?php echo html_escape($tq_c['student_name']); ?></p>
                                        <p class="tq-micro" style="margin:0"><?php echo tq_iso($tq_c['when_text']); ?></p>
                                        <?php /* المادة مع الصف: المعلم يفتح لأكثر من مادة، وسطر بلا
                                                 مادة يترك من يقرأ طلبه لا يعرف في أي مادة يحضر. */ ?>
                                        <?php $tq_tag = array_filter(array((string) ($tq_c['subject_name'] ?? ''),
                                            (int) $tq_c['grade_id'] > 0 ? $tq_c['grade_name'] : '')); ?>
                                        <?php if ($tq_tag): ?>
                                            <p class="tq-micro" style="margin:0"><?php echo html_escape(implode(' · ', $tq_tag)); ?></p>
                                        <?php endif; ?>
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
                                            <?php echo tq_icon('video', 16); ?> <?php echo t('ادخل الحصة'); ?>
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
                                          data-tq-confirm-title="<?php echo te('أنهيت الحصة مع ____؟', array(html_escape($tq_c['student_name']))); ?>"
                                          data-tq-confirm="<?php echo $tq_c['price'] > 0
                                              ? t('يغلق رابط اللقاء، ويقيد نصيبك في محفظتك.')
                                              : t('يغلق رابط اللقاء، ولا يستطيع الطالب الدخول بعده.'); ?>"
                                          data-tq-confirm-note="<?php echo te('لا تضغط قبل أن تنتهي فعلا — الرابط لا يفتح مرة أخرى.'); ?>"
                                          data-tq-confirm-ok="<?php echo te('نعم، انتهت'); ?>">
                                        <?php echo tq_csrf(); ?>
                                        <input type="hidden" name="session_id" value="<?php echo (int) $tq_c['id']; ?>">
                                        <button class="tq-btn tq-btn--secondary tq-btn--sm" type="submit">
                                            <?php echo tq_icon('check', 16); ?> <?php echo t('أعلن انتهاء الحصة'); ?>
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
                                    <?php /* المادة مع الصف: المعلم يفتح لأكثر من مادة، وسطر بلا
                                             مادة يترك من يقرأ طلبه لا يعرف في أي مادة يحضر. */ ?>
                                    <?php $tq_tag = array_filter(array((string) ($tq_c['subject_name'] ?? ''),
                                        (int) $tq_c['grade_id'] > 0 ? $tq_c['grade_name'] : '')); ?>
                                    <?php if ($tq_tag): ?>
                                        <p class="tq-micro" style="margin:0"><?php echo html_escape(implode(' · ', $tq_tag)); ?></p>
                                    <?php endif; ?>
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
        <?php
        /* صف الجدول — يطبع مرة للمحفوظ ومرة للقالب الفارغ الذي يستنسخه
           الزر. ودالة واحدة لأن نسختين تفترقان عند أول حقل يضاف: يظهر في
           السطر المحفوظ ولا يظهر في المضاف، فيحفظ المعلم سطرا ناقصا. */
        $tq_win_row = function ($w = null) use ($tq_days, $tq_grades, $tq_subs, $tq_scope, $tq_m, $tq_cfg,
                                                $tq_tracks, $tq_has_cur, $tq_has_fnd) {
            $dow  = $w ? (int) $w['dow'] : '';
            /* TQ-FOUNDATION — النوع الافتراضي هو **ما يملكه صاحبه**: معلم
               تأسيس بلا كورس واحد يفتح الشاشة فيجد السطر على «منهج»
               فيختار صفا لا يجده، ثم يرد عليه الخادم. والسطر يفتح على
               النوع الذي يستطيعه. */
            $kind = $w ? (string) $w['kind'] : (($tq_has_cur || !$tq_has_fnd) ? 'curriculum' : 'foundation');
            ob_start(); ?>
            <tr class="tq-winrow" data-tq-kind="<?php echo html_escape($kind); ?>">
                <?php /* عمود النوع أول الأعمدة لا آخرها: هو الذي يقرر ما
                         يقرأ بعده — صفا ومادة أم مسار تأسيس. وعمود يغير
                         معنى ما قبله يقرأ بأثر رجعي. */ ?>
                <?php if ($tq_has_cur && $tq_has_fnd): ?>
                    <td class="tq-winrow__kind" data-label="<?php echo te('نوع الوقت'); ?>">
                        <label class="tq-sr"><?php echo t('نوع الوقت'); ?></label>
                        <select class="tq-select" name="win_kind[]" data-tq-row-kind>
                            <option value="curriculum" <?php echo $kind === 'curriculum' ? 'selected' : ''; ?>>
                                <?php echo t('منهج — صف ومادة'); ?>
                            </option>
                            <option value="foundation" <?php echo $kind === 'foundation' ? 'selected' : ''; ?>>
                                <?php echo t('تأسيس — بلا صف'); ?>
                            </option>
                        </select>
                    </td>
                <?php endif; ?>
                <td class="tq-winrow__d" data-label="<?php echo te('يوم الأسبوع'); ?>">
                    <?php if (!($tq_has_cur && $tq_has_fnd)): ?>
                        <?php /* من يملك بابا واحدا لا يعرض له منتق بخيار
                                 واحد: حقل لا يبدل شيئا ضجيج، والقيمة ترسل
                                 مخفية — والمصفوفات الأربع تبقى متوازية. */ ?>
                        <input type="hidden" name="win_kind[]"
                               value="<?php echo $tq_has_cur ? 'curriculum' : 'foundation'; ?>">
                    <?php endif; ?>
                    <label class="tq-sr"><?php echo t('يوم الأسبوع'); ?></label>
                    <select class="tq-select" name="win_dow[]" data-tq-row-req>
                        <option value=""><?php echo t('— اختر يوما'); ?></option>
                        <?php foreach ($tq_days as $tq_di => $tq_dn): ?>
                            <option value="<?php echo (int) $tq_di; ?>"
                                <?php echo ($dow !== '' && (int) $dow === (int) $tq_di) ? 'selected' : ''; ?>>
                                <?php echo html_escape($tq_dn); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td class="tq-winrow__t" data-label="<?php echo te('من الساعة'); ?>">
                    <label class="tq-sr"><?php echo t('من الساعة'); ?></label>
                    <input class="tq-input" type="time" dir="ltr" name="win_from[]" data-tq-row-req
                           value="<?php echo $w ? html_escape($w['from_text']) : ''; ?>">
                </td>
                <td class="tq-winrow__t" data-label="<?php echo te('إلى الساعة'); ?>">
                    <label class="tq-sr"><?php echo t('إلى الساعة'); ?></label>
                    <input class="tq-input" type="time" dir="ltr" name="win_to[]" data-tq-row-req
                           value="<?php echo $w ? html_escape($w['to_text']) : ''; ?>">
                </td>
                <?php /* TQ-FOUNDATION — خلية المسار وخليتا الصف والمادة
                         **تعرضان ولا تعطلان**: الحقل المعطل لا يرسل، وثلاث
                         مصفوفات متوازية يسقط من إحداها عنصر تعني أن صف
                         الثلاثاء يأخذ مادة الأربعاء. فالإخفاء بصنف على
                         الصف، والقيم كلها تصل، والخادم يهمل ما لا يعني
                         النوع الذي اختير. */ ?>
                <td data-label="<?php echo te('مسار التأسيس'); ?>" class="tq-winrow__fnd">
                    <label class="tq-sr"><?php echo t('مسار التأسيس'); ?></label>
                    <?php if ($tq_tracks): ?>
                        <select class="tq-select" name="win_track[]" data-tq-row-track>
                            <option value="0"><?php echo t('— اختر مسارا'); ?></option>
                            <?php foreach ($tq_tracks as $tq_tid => $tq_t): ?>
                                <option value="<?php echo (int) $tq_tid; ?>"
                                    <?php echo ($w && (int) $w['track_id'] === (int) $tq_tid) ? 'selected' : ''; ?>>
                                    <?php echo html_escape($tq_t['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="hidden" name="win_track[]" value="0">
                    <?php endif; ?>
                    <span class="tq-winrow__na" aria-hidden="true">—</span>
                </td>
                <td data-label="<?php echo te('الصف'); ?>" class="tq-winrow__cur">
                    <label class="tq-sr"><?php echo t('الصف'); ?></label>
                    <?php /* بلا «كل الصفوف»: خيار يفتح الوقت لكل من في
                             المنصة يجعل معلم الرابع الابتدائي يستقبل طالبا
                             في الثالث المتوسط. والوقت القديم بصفر يعرض
                             «— اختر صفا» فيقرأ صاحبه أن عليه أن يختار. */ ?>
                    <select class="tq-select" name="win_grade[]" data-tq-row-req data-tq-row-grade>
                        <option value="0"><?php echo t('— اختر صفا'); ?></option>
                        <?php foreach ($tq_grades as $tq_gid => $tq_gname): ?>
                            <option value="<?php echo (int) $tq_gid; ?>"
                                <?php echo ($w && (int) $w['grade_id'] === (int) $tq_gid) ? 'selected' : ''; ?>>
                                <?php echo html_escape($tq_gname); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="tq-winrow__na" aria-hidden="true">—</span>
                </td>
                <?php /* المادة بجوار الصف: المعلم يدرس أكثر من مادة، ومن
                         يفتح «الأحد ١٠–٢ للثالث» بلا مادة يستقبل طالبا جاء
                         يسأل في غير مادته. والقائمة تضيق بالصف المختار
                         (`data-tq-grade`) بالوصف نفسه الذي يفحص به الخادم. */ ?>
                <td data-label="<?php echo te('المادة'); ?>" class="tq-winrow__cur">
                    <label class="tq-sr"><?php echo t('المادة'); ?></label>
                    <select class="tq-select" name="win_subject[]" data-tq-row-req data-tq-row-subject>
                        <option value="0"><?php echo t('— اختر مادة'); ?></option>
                        <?php foreach ($tq_subs as $tq_sid => $tq_sname): ?>
                            <?php /* الصفوف التي تدرس فيها هذه المادة تكتب على
                                     الخيار، فيخفيها السكربت متى اختير غيرها.
                                     وبلا سكربت تبقى معروضة ويرد الخادم بخطأ
                                     يسمي الصف والمادة — لا رفض صامت. */ ?>
                            <?php
                            $tq_for = array();
                            foreach ($tq_scope['pairs'] as $tq_pg => $tq_ps) {
                                if (isset($tq_ps[$tq_sid])) $tq_for[] = (int) $tq_pg;
                            }
                            ?>
                            <option value="<?php echo (int) $tq_sid; ?>"
                                    data-tq-grades="<?php echo html_escape(implode(',', $tq_for)); ?>"
                                <?php echo ($w && (int) $w['subject_id'] === (int) $tq_sid) ? 'selected' : ''; ?>>
                                <?php echo html_escape($tq_sname); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="tq-winrow__na" aria-hidden="true">—</span>
                </td>
                <?php /* عدد المواعيد يقرأ قبل الحفظ: «من ١٠ إلى ٢» أربع
                         ساعات تعني أربعة طلاب لا طالبا واحدا، ومن لا يقرأ
                         الرقم يظن أنه فتح موعدا فيرد ثلاثة. */ ?>
                <td class="tq-winrow__n" data-label="<?php echo te('مواعيد'); ?>">
                    <span class="tq-micro" data-tq-row-count>
                        <?php echo $w ? tq_iso((string) (int) $w['slots']) : '—'; ?>
                    </span>
                </td>
                <td class="tq-winrow__x">
                    <button type="button" class="tq-btn tq-btn--ghost tq-btn--sm" data-tq-row-del
                            aria-label="<?php echo te('احذف هذا الوقت'); ?>">
                        <?php echo tq_icon('close', 14); ?>
                    </button>
                </td>
            </tr>
            <?php return ob_get_clean();
        };
        ?>
        <form class="tq-card tq-card--panel" method="post"
              action="<?php echo base_url('teacher/sessions/save'); ?>">
            <?php echo tq_csrf(); ?>
            <fieldset style="border:0;padding:0;margin:0">
                <legend class="tq-h2" style="padding:0"><?php echo t('أوقاتي المتاحة'); ?></legend>
                <p class="tq-caption">
                    <?php echo t('اكتب ساعات عملك يوما بيوم — من الساعة كذا إلى الساعة كذا. الطالب لا يرى غيرها، فلا يصلك طلب في وقت لا تعمل فيه. والأوقات أسبوعية دائمة: تكتب مرة وتتكرر كل أسبوع حتى تغيرها.'); ?>
                </p>
                <?php /* الوقت إتاحة لا حصة: يفرش إلى مواعيد بطول الحصة
                         يحجزها طلاب مختلفون، لا موعدا واحدا يشغله كلها. */ ?>
                <p class="tq-caption">
                    <strong><?php echo t('كل وقت يفرش إلى مواعيد بطول'); ?>
                    <?php echo tq_iso($tq_cfg['minutes'] . t(' دقيقة')); ?></strong>
                    <?php echo t('— فمن العاشرة إلى الثانية يعطي ____ يحجزها طلاب مختلفون. ومدة الحصة تحددها الإدارة.', array(tq_iso((string) max(1, intdiv(240, max(1, $tq_cfg['minutes']))) . t(' مواعيد')))); ?>
                </p>
                <?php /* الصف هو نصف التغيير: من يدرس صفين في يوم واحد
                         يفتح لكل صف وقته، فيرى طالب الثالث مواعيد الثالث
                         وحدها ولا يطلب موعدا سيعتذر عنه معلمه. */ ?>
                <?php if ($tq_has_cur): ?>
                <p class="tq-caption">
                    <strong><?php echo t('ولكل وقت صفه'); ?></strong>
                    <?php echo t('— الطالب لا يرى إلا مواعيد صفه هو.'); ?>
                    <?php /* الصفوف المعروضة ليست صفوف المنصة: هي صفوف كورساته.
                             وقولها هنا يمنع سؤال «أين بقية الصفوف؟». */ ?>
                    <?php echo t('والصفوف والمواد المعروضة هي صفوف كورساتك وموادها وحدها — الحصة شرح لمنهج صف بعينه في مادة بعينها. والمادة تتبع الصف الذي تختاره.'); ?>
                </p>
                <?php endif; ?>

                <?php /* TQ-FOUNDATION — وقت التأسيس بلا صف ولا مادة، وقولها
                         هنا يمنع سؤال «أين الصف؟» عند من فتح سطرا فاختفى
                         عموده. والفرق ليس شكليا: التأسيس مستوى لا مقرر،
                         فطالب في أي صف يحجزه. */ ?>
                <?php if ($tq_has_fnd): ?>
                <p class="tq-caption">
                    <strong><?php echo t('ووقت التأسيس بلا صف ولا مادة'); ?></strong>
                    <?php echo t('— يكفيه مسار من مساراتك، ويحجزه الطالب أيا كان صفه. والتأسيس مستوى لا منهج صف.'); ?>
                    <?php echo t('ومساراتك هي ما أسندته إليك الإدارة:'); ?>
                    <strong><?php
                        $tq_tn = array();
                        foreach ($tq_tracks as $tq_t) $tq_tn[] = $tq_t['name'];
                        echo html_escape(implode(' · ', $tq_tn));
                    ?></strong>
                </p>
                <?php endif; ?>

                <?php /* معلم بلا نطاق: الشاشة تقول السبب والطريق ولا تعرض
                         جدولا يحفظ فيه ما يرده الخادم. وجدول يقبل الكتابة
                         ثم يرد كل حفظ يقرأ عطلا.
                         والبابان يذكران معا: من رد لأنه لا كورس له قد يكون
                         معلم تأسيس لا كورس له أصلا ولا ينبغي أن يفتح كورسا
                         ليدرس الحروف. */ ?>
                <?php if (!$tq_has_cur && !$tq_has_fnd): ?>
                    <div class="tq-pastel tq-pastel--peach">
                        <span class="tq-pastel__label tq-micro"><?php echo t('لا نطاق لك بعد'); ?></span>
                        <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                            <?php echo t('وللأوقات بابان: حصص المنهج تفتح لصف بعينه في مادة بعينها — وصفوفك وموادك تشتق من كورساتك ولا كورس لك بعد. وحصص التأسيس لا تحتاج كورسا ولا صفا، وإنما مسار تأسيس تسنده إليك الإدارة. افتح كورسا في صفك، أو راجع الإدارة لتسند إليك مسار تأسيس.'); ?>
                        </p>
                        <a class="tq-btn tq-btn--secondary tq-btn--sm" style="margin-block-start:var(--tq-space-m)"
                           href="<?php echo base_url('teacher/courses'); ?>"><?php echo t('كورساتي'); ?></a>
                    </div>
                <?php else: ?>

                <div class="tq-table-wrap" data-tq-rows data-tq-row-min="<?php echo (int) $tq_cfg['minutes']; ?>">
                    <table class="tq-table tq-table--rows tq-winrows<?php
                        echo $tq_has_cur ? ' tq-winrows--cur' : '';
                        echo $tq_has_fnd ? ' tq-winrows--fnd' : ''; ?>">
                        <caption class="tq-sr"><?php echo t('أوقاتك المتاحة في أيام الأسبوع'); ?></caption>
                        <thead>
                            <tr>
                                <?php if ($tq_has_cur && $tq_has_fnd): ?>
                                    <th scope="col"><?php echo t('نوع الوقت'); ?></th>
                                <?php endif; ?>
                                <th scope="col"><?php echo t('يوم الأسبوع'); ?></th>
                                <th scope="col"><?php echo t('من الساعة'); ?></th>
                                <th scope="col"><?php echo t('إلى الساعة'); ?></th>
                                <th scope="col" class="tq-winrow__fnd"><?php echo t('مسار التأسيس'); ?></th>
                                <th scope="col" class="tq-winrow__cur"><?php echo t('الصف'); ?></th>
                                <th scope="col" class="tq-winrow__cur"><?php echo t('المادة'); ?></th>
                                <th scope="col" class="tq-winrow__n"><?php echo t('مواعيد'); ?></th>
                                <th scope="col"><span class="tq-sr"><?php echo t('حذف'); ?></span></th>
                            </tr>
                        </thead>
                        <tbody data-tq-rows-body>
                            <?php foreach ($tq_windows as $tq_w) echo $tq_win_row($tq_w); ?>
                            <?php /* سطر فارغ دائما في الذيل: بلا جافاسكربت
                                     يبقى للشاشة باب واحد يضاف منه وقت. */ ?>
                            <?php echo $tq_win_row(null); ?>
                        </tbody>
                    </table>
                </div>

                <template data-tq-row-tpl><?php echo $tq_win_row(null); ?></template>

                <div class="tq-row" style="gap:var(--tq-space-m);flex-wrap:wrap;margin-block-start:var(--tq-space-l)">
                    <button class="tq-btn tq-btn--secondary tq-btn--sm" type="button" data-tq-row-add>
                        <?php echo tq_icon('plus', 16); ?> <?php echo t('أضف وقتا'); ?>
                    </button>
                </div>

                <button class="tq-btn tq-btn--primary" type="submit" style="margin-block-start:var(--tq-space-xl)"
                        aria-describedby="tq-slots-note">
                    <?php echo t('حفظ أوقاتي'); ?>
                </button>
                <p class="tq-field__msg tq-field__hint" id="tq-slots-note" style="margin-block-start:var(--tq-space-m)">
                    <?php /* الحفظ استبدال لا إضافة، وقولها هنا يمنع أن يفرغ
                             المعلم السطور ظانا أنه «لم يغير شيئا». */ ?>
                    <?php echo t('الحفظ يستبدل قائمتك كلها: ما يبقى في الجدول هو أوقاتك، وما حذفت منه يغلق. وما حجزه طالب فعلا لا يغلق برفعه من هنا — بل بالاعتذار عن طلبه.'); ?>
                </p>
                <?php endif; /* $tq_grades && $tq_subs — بلا نطاق لا جدول ولا زر حفظ */ ?>
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
                    <?php echo t('(____)، والباقي ____ عمولة المنصة. ومدة الحصة ____.', array(tq_iso(rtrim(rtrim(number_format($tq_pricing['percent'], 2), '0'), '.') . t('٪')), $tq_sar($tq_pricing['platform']), tq_iso($tq_cfg['minutes'] . t(' دقيقة')))); ?>
                </p>
                <p class="tq-micro" style="margin:var(--tq-space-m) 0 0">
                    <?php echo $tq_pricing['from_teacher']
                        ? t('تسعيرة خاصة بك حددتها الإدارة.')
                        : t('التسعيرة العامة للمنصة، وتحددها الإدارة.'); ?>
                    <?php echo t('ونصيبك يقيد في محفظتك'); ?> <strong><?php echo t('حين تعلن انتهاء الحصة'); ?></strong> <?php echo t('لا حين يدفع الطالب.'); ?>
                </p>
            </div>
        <?php endif; ?>

        <div class="tq-pastel tq-pastel--sky">
            <span class="tq-pastel__label tq-micro"><?php echo t('قاعدة الحصص'); ?></span>
            <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                <?php echo tq_iso(t('طلب بلا رد ') . $tq_cfg['pay_hours']
                    . t(' ساعة يلغى تلقائيا ويعاد للطالب. الاعتذار المبكر أفضل للطالب من انتظار لا ينتهي.')); ?>
            </p>
            <?php if ($tq_paid): ?>
                <p class="tq-pastel__body" style="margin:var(--tq-space-m) 0 0">
                    <?php echo tq_iso(t('والموعد لا يثبت لك إلا بعد أن يدفع الطالب خلال ')
                        . $tq_cfg['pay_hours'] . t(' ساعة من تأكيدك — وحتى ذلك الحين يبقى معروضا لغيره.')); ?>
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
                    <span class="tq-caption"><?php echo t('أوقات أسبوعية'); ?></span>
                    <?php echo tq_num($tq_sum['windows']); ?>
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
