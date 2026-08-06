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
$tq_title = 'الحصص';
$tq_sub   = 'طلبات الحجز وأوقاتك المتاحة';
$tq_icon  = 'video';

$tq_uid = (int) $this->session->userdata('user_id');

/* النموذج يحمل عبر get_instance(): العارض في CI3 ينسخ خصائص المتحكم إلى
   المحمل مرة واحدة قبل التصيير، فما حمل بعد بدء التصيير لا يظهر في `$this`. */
$tq_CI = get_instance();
$tq_CI->load->model('taqdar_sessions_model');
$tq_m = $tq_CI->taqdar_sessions_model;

/* ---- الكتابة قبل أي إخراج، ثم تحويل: تحديث الصفحة لا يعيد الإرسال ------
   والملكية والحالة تفحصان في النموذج داخل الاستعلام نفسه. */
$tq_do = (string) $this->input->post('tq_action');
if ($tq_do !== '') {
    if ($tq_do === 'save_slots') {
        $tq_n = $tq_m->save_week($tq_uid, (array) $this->input->post('slots'));
        $this->session->set_flashdata(
            'flash_message',
            $tq_n > 0 ? 'حفظت أوقاتك: ' . $tq_n . ' فترة متاحة هذا الأسبوع.'
                      : 'حفظ اختيارك: لا فترات متاحة، فلن يصلك طلب حتى تفتح وقتا.'
        );
    } elseif ($tq_do === 'confirm' || $tq_do === 'decline') {
        $tq_r = $tq_m->decide((int) $this->input->post('session_id'), $tq_uid, $tq_do);
        $this->session->set_flashdata($tq_r['ok'] ? 'flash_message' : 'error_message', $tq_r['msg']);
    }
    redirect(site_url('teacher/sessions'), 'refresh');
}

/* ---- القراءة ---------------------------------------------------------- */
$tq_requests  = $tq_m->requests_for_teacher($tq_uid, ['requested']);
$tq_confirmed = $tq_m->requests_for_teacher($tq_uid, ['confirmed', 'live']);

$tq_saved_slots = $tq_m->week_keys($tq_uid);
$tq_days        = $tq_m->days();
$tq_periods     = $tq_m->periods();

$tq_ses_photo = function ($image) {
    return !empty($image) ? base_url('uploads/user_image/' . $image) : tq_asset('brand/icon.png');
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
                <h2 id="tq-req-h">طلبات الحجز</h2>
                <?php if ($tq_requests): ?>
                    <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_requests) . TQ_PDI; ?></span>
                <?php endif; ?>
            </div>

            <?php if ($tq_requests): ?>
                <div class="tq-card">
                    <ul class="tq-stack">
                        <?php foreach ($tq_requests as $tq_r): ?>
                            <li class="tq-row" style="gap:var(--tq-space-l);padding-block:var(--tq-space-m);border-block-end:1px solid var(--tq-line)">
                                <img class="tq-avatar" src="<?php echo html_escape($tq_ses_photo($tq_r['image'])); ?>"
                                     alt="<?php echo html_escape('صورة ' . $tq_r['student_name']); ?>">
                                <div style="flex:1;min-inline-size:0">
                                    <p class="tq-strong" style="margin:0;color:var(--tq-navy)"><?php echo html_escape($tq_r['student_name']); ?></p>
                                    <p class="tq-micro" style="margin:0"><?php echo tq_iso($tq_r['when_text']); ?></p>
                                </div>
                                <form method="post" action="<?php echo base_url('teacher/sessions'); ?>" style="display:inline">
                                    <input type="hidden" name="tq_action" value="confirm">
                                    <input type="hidden" name="session_id" value="<?php echo (int) $tq_r['id']; ?>">
                                    <button class="tq-btn tq-btn--mastery tq-btn--sm" type="submit">تأكيد</button>
                                </form>
                                <form method="post" action="<?php echo base_url('teacher/sessions'); ?>" style="display:inline">
                                    <input type="hidden" name="tq_action" value="decline">
                                    <input type="hidden" name="session_id" value="<?php echo (int) $tq_r['id']; ?>">
                                    <button class="tq-btn tq-btn--secondary tq-btn--sm" type="submit">اعتذار</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <div class="tq-card tq-empty">
                    <span class="tq-icon-box tq-pastel--lilac" style="color:var(--tq-lilac-ink)" aria-hidden="true"><?php echo tq_icon('calendar', 24); ?></span>
                    <h3 class="tq-empty__title">لا طلبات حجز الآن</h3>
                    <p class="tq-empty__text">
                        <?php echo tq_iso('حين يطلب أحد طلابك حصة خاصة، يظهر طلبه هنا بموعده، فتؤكده أو تعتذر عنه خلال 24 ساعة. حدد أوقاتك المتاحة أدناه ليعرف الطالب متى يطلب.'); ?>
                    </p>
                    <a class="tq-btn tq-btn--secondary" href="<?php echo base_url('teacher'); ?>">عودة إلى اللوحة</a>
                </div>
            <?php endif; ?>
        </section>

        <!-- حصص مؤكدة: أثر التأكيد يرى، فلا يختفي الطلب بلا مآل -->
        <?php if ($tq_confirmed): ?>
            <section class="tq-section" aria-labelledby="tq-ok-h">
                <div class="tq-sectionhead">
                    <h2 id="tq-ok-h">حصص مؤكدة</h2>
                    <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_confirmed) . TQ_PDI; ?></span>
                </div>
                <div class="tq-card">
                    <ul class="tq-stack">
                        <?php foreach ($tq_confirmed as $tq_c): ?>
                            <?php $tq_b = $tq_m->status_badge($tq_c['status']); ?>
                            <li class="tq-row" style="gap:var(--tq-space-l);padding-block:var(--tq-space-m);border-block-end:1px solid var(--tq-line)">
                                <img class="tq-avatar" src="<?php echo html_escape($tq_ses_photo($tq_c['image'])); ?>"
                                     alt="<?php echo html_escape('صورة ' . $tq_c['student_name']); ?>">
                                <div style="flex:1;min-inline-size:0">
                                    <p class="tq-strong" style="margin:0;color:var(--tq-navy)"><?php echo html_escape($tq_c['student_name']); ?></p>
                                    <p class="tq-micro" style="margin:0"><?php echo tq_iso($tq_c['when_text']); ?></p>
                                </div>
                                <?php echo tq_badge($tq_b[0], $tq_b[1]); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>
        <?php endif; ?>

        <!-- الأوقات المتاحة: الأسبوع يبدأ الأحد -->
        <form class="tq-card tq-card--panel" method="post"
              action="<?php echo base_url('teacher/sessions'); ?>">
            <input type="hidden" name="tq_action" value="save_slots">
            <fieldset style="border:0;padding:0;margin:0">
                <legend class="tq-h2" style="padding:0">أوقاتي المتاحة</legend>
                <p class="tq-caption">
                    اختر الفترات التي تقبل فيها حصصا خاصة. الطالب لا يرى غيرها، فلا يصلك طلب في وقت لا تعمل فيه.
                    والاختيار يسري على الأيام السبعة القادمة، وما حجز منه لا يلغى برفع العلامة — بل بالاعتذار عن طلبه.
                </p>

                <div style="overflow-x:auto">
                    <table class="tq-table">
                        <caption class="tq-sr">أوقاتك المتاحة في أيام الأسبوع</caption>
                        <thead>
                            <tr>
                                <th scope="col">اليوم</th>
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
                    حفظ أوقاتي
                </button>
                <p class="tq-field__msg tq-field__hint" id="tq-slots-note" style="margin-block-start:var(--tq-space-m)">
                    الحفظ فوري: كل فترة تختارها تصير موعدا يراه الطالب في «حصص بالطلب».
                </p>
            </fieldset>
        </form>
    </div>

    <aside class="tq-aside">
        <div class="tq-pastel tq-pastel--sky">
            <span class="tq-pastel__label tq-micro">قاعدة الحصص</span>
            <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                <?php echo tq_iso('طلب بلا رد 24 ساعة يلغى تلقائيا ويعاد للطالب. الاعتذار المبكر أفضل للطالب من انتظار لا ينتهي.'); ?>
            </p>
        </div>

        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title">هذا الأسبوع</h2></div>
            <ul class="tq-stack">
                <li class="tq-row tq-row--between">
                    <span class="tq-caption">طلبات معلقة</span>
                    <?php echo tq_num(count($tq_requests)); ?>
                </li>
                <li class="tq-row tq-row--between">
                    <span class="tq-caption">حصص مؤكدة</span>
                    <?php echo tq_num(count($tq_confirmed)); ?>
                </li>
                <li class="tq-row tq-row--between">
                    <span class="tq-caption">فترات متاحة</span>
                    <?php echo tq_num(count($tq_saved_slots)); ?>
                </li>
            </ul>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
