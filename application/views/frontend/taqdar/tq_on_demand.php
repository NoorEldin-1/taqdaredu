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
 * يحتجز عليه مال ولا تجمد به بطاقة. «بانتظار التأكيد» تعني بالضبط
 * أن شيئا لم يدفع بعد.
 *
 * موصول بالقاعدة: course + category (عدد معلمي كل مادة) · `availability_slots`
 * (المعلمون المتاحون ومواعيدهم) · `tutoring_sessions` (طلبات الطالب وحالتها).
 * وكان هذا الملف يعد الإتاحة «بلا جدول» وهي في `availability_slots` أمامه.
 *
 * بلا مصدر بعد: أسعار الحصص وتقييمات المعلمين وسنوات خبرتهم — لا عمود لها
 * في taqd_lms، فلا تعرض أرقام مخترعة بجوار اسم معلم حقيقي.
 */

$tq_uid = (int) $this->session->userdata('user_id');

/* النموذج يحمل عبر get_instance(): العارض في CI3 ينسخ خصائص المتحكم إلى
   المحمل مرة واحدة قبل التصيير، فما حمل بعد بدء التصيير لا يظهر في `$this`. */
$tq_CI = get_instance();
$tq_CI->load->model('taqdar_sessions_model');
$tq_m = $tq_CI->taqdar_sessions_model;

/* ---- الكتابة قبل أي إخراج، ثم تحويل: تحديث الصفحة لا يعيد الطلب ------
   وكل شرط (الموعد قائم ومفتوح ولم يمض ولا طلب سابق عليه) يفحص في النموذج
   داخل الاستعلام لا في الواجهة. */
if ((string) $this->input->post('tq_action') === 'request_session') {
    $tq_res  = $tq_m->request_session($tq_uid, (int) $this->input->post('slot_id'));
    $this->session->set_flashdata($tq_res['ok'] ? 'flash_message' : 'error_message', $tq_res['msg']);

    $tq_back = (string) $this->input->post('subject', true);
    redirect(site_url('taqdar/on-demand') . ($tq_back !== '' ? '?subject=' . rawurlencode($tq_back) : ''), 'refresh');
}

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

        <!-- البانر الترويجي: الفعل الأساسي الوحيد في الشاشة -->
        <section class="tq-s-banner tq-section tq-enter">
            <div class="tq-s-banner__body">
                <p class="tq-eyebrow">حصص مباشرة</p>
                <h2 class="tq-display" style="margin-block-end:var(--tq-space-s)">تعلم بطريقتك الخاصة</h2>
                <p class="tq-body">
                    احجز حصة مباشرة مع معلم خبير، واحصل على شرح مخصص لاحتياجك أنت —
                    ولا يخصم مبلغ الحصة إلا بعد تأكيد المعلم.
                </p>
                <a class="tq-btn tq-btn--primary" href="<?php echo base_url('taqdar/on-demand#tq-tutors'); ?>">
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
                           href="<?php echo base_url('taqdar/on-demand?subject=' . $s['id']); ?>"
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
                        <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('taqdar/on-demand'); ?>">
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
                    <?php echo tq_s_empty(
                        'users', 'mint',
                        'لا معلم متاح الآن',
                        'حين يفتح المعلمون أوقاتهم يظهر كل واحد هنا باسمه ومادته ومواعيده المتاحة، وبجواره زر طلب مباشر.',
                        'نبهني عند توفر معلم',
                        base_url('student/settings')
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
                                </p>
                            </div>
                        </div>

                        <form method="post" action="<?php echo base_url('student/on-demand'); ?>"
                              class="tq-row" style="gap:var(--tq-space-m);flex-wrap:wrap;margin-block-start:var(--tq-space-l)">
                            <input type="hidden" name="tq_action" value="request_session">
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
                $steps = [
                    ['اختر المادة',  'اختر المادة التي تحتاج مساعدة فيها.'],
                    ['اختر معلما', 'تصفح المعلمين المتاحين واختر الأنسب لك.'],
                    ['حدد الوقت',   'اختر الوقت المناسب لك من الأوقات المتاحة.'],
                    ['ابدأ الحصة',  'انضم للحصة واستفد من شرح مباشر ومخصص.'],
                ];
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
                لا يخصم مبلغ الحصة إلا بعد تأكيد المعلم.
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
                        <li class="tq-s-item">
                            <img class="tq-avatar" src="<?php echo html_escape($tq_ses_photo($b['image'])); ?>"
                                 alt="<?php echo html_escape('صورة ' . $b['tutor']); ?>">
                            <span class="tq-s-item__body">
                                <span class="tq-s-item__t tq-s-trunc"><?php echo html_escape($b['subject']); ?></span>
                                <span class="tq-s-item__s tq-s-trunc"><?php echo html_escape($b['tutor']); ?></span>
                                <span class="tq-s-item__s"><?php echo tq_iso($b['when_text']); ?></span>
                            </span>
                            <?php echo tq_badge($badge[0], $badge[1]); ?>
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
