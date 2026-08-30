<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * TQ-TEACHER-ADD — صفحة المعلم في اللوحة.
 *
 * كان صف المعلم في «كل الحسابات» يقول أربعة أشياء — اسما وبريدا وحالا
 * وتاريخا — والسؤال الذي يفتح المسؤول الشاشة لأجله غيرها: **ماذا يدرس
 * هذا؟ وكم طالبا عنده؟ وهل دخل في مال؟** ولم يكن في اللوحة موضع واحد
 * يجيب، فيبحث في «الكورسات» بالاسم ثم في «المحافظ» بالمعرف ثم يخمن.
 *
 * وهي شاشة **قراءة**: التحرير بابه `teacher_edit`، فمن فتحها ليقرأ لا
 * يحفظ صفا لم يقصده. والفعلان الوحيدان فيها الحذف والذهاب إلى التعديل.
 */
$tq_name  = trim($row['first_name'] . ' ' . $row['last_name']);
$tq_on    = (int) $row['status'] === 1;
$tq_img   = $this->user_model->get_user_image_url((int) $row['id']);
$tq_phone = tq_phone_pretty((string) $row['phone']);

$tq_cards = array(
    array(t('كورساته'),        (int) $stats['courses'],   t('المنشور منها') . (int) $stats['published'], 'book'),
    array(t('دروسه'),          (int) $stats['lessons'],   t('في كل كورساته'),                             'play'),
    array(t('طلابه'),          (int) $stats['students'],  t('المسجلون في كورساته'),                        'users'),
    array(t('برامجه'),         (int) $stats['paths'],     t('مسارات تعرض كورساته'),                        'route'),
    array(t('حصصه الخاصة'),    (int) $stats['sessions'],  t('بكل حالاتها'),                                'video'),
    array(t('قيود إيراده'),    (int) $stats['shares'],    t('من بيع الباقات'),                             'wallet'),
);
?>

<?php tqa_head(t('المعلم:') . $tq_name,
    t('ما يدرسه، وكم عنده، وما دخل فيه من مال — قبل أن تعدل أو تحذف.'),
    'user-check'); ?>

<div class="tqa-card tqa-section">
    <div style="display:flex;gap:var(--tq-space-l);align-items:flex-start;flex-wrap:wrap">
        <img src="<?php echo html_escape($tq_img); ?>" alt="" width="72" height="72"
             style="inline-size:72px;block-size:72px;border-radius:50%;object-fit:cover;flex:none"
             loading="lazy" decoding="async">

        <div style="flex:1;min-inline-size:240px">
            <h2 style="margin:0 0 var(--tq-space-xs);font:var(--tq-type-h2);color:var(--tq-navy)">
                <?php echo html_escape($tq_name ?: t('بلا اسم')); ?>
                <span class="tqa-badge tqa-badge--<?php echo $tq_on ? 'ok' : 'danger'; ?>">
                    <?php echo $tq_on ? t('مفتوح') : t('مغلق'); ?>
                </span>
                <?php if ((int) $row['is_public'] === 1): ?>
                    <span class="tqa-badge tqa-badge--info"><?php echo t('معروض علنا'); ?></span>
                <?php endif; ?>
            </h2>

            <?php if (trim((string) $row['title']) !== ''): ?>
                <p style="margin:0 0 var(--tq-space-s);color:var(--tq-text2);font:var(--tq-type-caption)">
                    <?php echo html_escape($row['title']); ?>
                </p>
            <?php endif; ?>

            <dl class="tqa-fieldgrid" style="margin:0">
                <div>
                    <dt class="tqa-field__label"><?php echo t('البريد'); ?></dt>
                    <dd class="tqa-num" style="margin:0" dir="ltr"><?php echo html_escape($row['email']); ?></dd>
                </div>
                <div>
                    <dt class="tqa-field__label"><?php echo t('الجوال'); ?></dt>
                    <dd class="tqa-num" style="margin:0" dir="ltr">
                        <?php echo $tq_phone !== '' ? html_escape($tq_phone) : '—'; ?>
                    </dd>
                </div>
                <div>
                    <dt class="tqa-field__label"><?php echo t('التسجيل'); ?></dt>
                    <dd class="tqa-num" style="margin:0"><?php
                        echo !empty($row['date_added']) ? date('Y-m-d', (int) $row['date_added']) : '—';
                    ?></dd>
                </div>
                <div>
                    <dt class="tqa-field__label"><?php echo t('المعرف'); ?></dt>
                    <dd class="tqa-num" style="margin:0">#<?php echo (int) $row['id']; ?></dd>
                </div>
            </dl>
        </div>
    </div>

    <?php if (trim((string) $row['skills']) !== ''): ?>
        <p style="margin:var(--tq-space-l) 0 0;font:var(--tq-type-caption);color:var(--tq-text2)">
            <strong><?php echo t('المواد:'); ?></strong> <?php echo html_escape($row['skills']); ?>
        </p>
    <?php endif; ?>

    <?php if (trim((string) $row['biography']) !== ''): ?>
        <p style="margin:var(--tq-space-s) 0 0;font:var(--tq-type-caption)">
            <?php echo nl2br(html_escape($row['biography'])); ?>
        </p>
    <?php endif; ?>

    <div class="tqa-actions" style="margin-block-start:var(--tq-space-l)">
        <a class="tqa-btn tqa-btn--primary"
           href="<?php echo site_url('taqdar_admin/teacher_edit/' . (int) $row['id']); ?>">
            <?php echo tq_icon('edit', 16); ?> عدل بياناته
        </a>

        <?php /* الفتح والإغلاق POST كما في القائمة: يغير من يستطيع الدخول. */ ?>
        <form action="<?php echo site_url('taqdar_admin/people_toggle'); ?>" method="post" style="margin:0"
              data-tqa-confirm-title="<?php echo $tq_on ? t('إغلاق الحساب') : t('فتح الحساب'); ?>"
              data-tqa-confirm="<?php echo $tq_on
                  ? t('لن يستطيع صاحبه الدخول. ولا يحذف شيء: كورساته وطلابه ومحفظته تبقى كما هي.')
                  : t('سيستطيع صاحبه الدخول من جديد، ويستعيد كل ما كان له.'); ?>"
              data-tqa-confirm-ok="<?php echo $tq_on ? t('أغلق الحساب') : t('افتح الحساب'); ?>"
              <?php echo $tq_on ? 'data-tqa-confirm-tone="danger"' : ''; ?>>
            <?php echo tq_csrf(); ?>
            <input type="hidden" name="user_id" value="<?php echo (int) $row['id']; ?>">
            <input type="hidden" name="back" value="role=teacher">
            <button class="tqa-btn tqa-btn--ghost" type="submit">
                <?php echo tq_icon($tq_on ? 'lock' : 'key', 16); ?>
                <?php echo $tq_on ? t('أغلق الحساب') : t('افتح الحساب'); ?>
            </button>
        </form>

        <?php if (empty($blockers)): ?>
            <form action="<?php echo site_url('taqdar_admin/teacher_delete'); ?>" method="post" style="margin:0"
                  data-tqa-confirm-title="<?php echo te('حذف الحساب'); ?>"
                  data-tqa-confirm="يحذف الحساب وما لا معنى له بلا صاحبه. ولا رجعة فيه."
                  data-tqa-confirm-ok="احذف الحساب"
                  data-tqa-confirm-tone="danger">
                <?php echo tq_csrf(); ?>
                <input type="hidden" name="user_id" value="<?php echo (int) $row['id']; ?>">
                <button class="tqa-btn tqa-btn--danger" type="submit">
                    <?php echo tq_icon('trash', 16); ?> احذف الحساب
                </button>
            </form>
        <?php endif; ?>

        <a class="tqa-btn tqa-btn--ghost" href="<?php echo site_url('taqdar_admin/people?role=teacher'); ?>">
            <?php echo t('عد إلى الحسابات'); ?>
        </a>
    </div>
</div>

<div class="tqa-grid tqa-grid--3 tqa-section">
    <?php foreach ($tq_cards as [$tq_label, $tq_n, $tq_sub, $tq_ic]): ?>
        <div class="tqa-card">
            <div class="tqa-card__head" style="padding:0 0 var(--tq-space-s)">
                <span class="tqa-iconbox tqa-sky" aria-hidden="true"><?php echo tq_icon($tq_ic, 18); ?></span>
                <h2 style="font:var(--tq-type-caption)"><?php echo html_escape($tq_label); ?></h2>
            </div>
            <p class="tqa-num" style="margin:0;font:var(--tq-type-h1);color:var(--tq-navy)">
                <?php echo (int) $tq_n; ?>
            </p>
            <p style="margin:2px 0 0;font:var(--tq-type-micro);color:var(--tq-text2)">
                <?php echo html_escape($tq_sub); ?>
            </p>
        </div>
    <?php endforeach; ?>
</div>

<?php /* TQ-TEACHER-DELETE — ولماذا لا يحذف يقال بالرقم لا بـ«غير مسموح».
         من قرأ «لا يحذف» بلا سبب يظن الشاشة معطلة، ومن قرأ «له ١٢ كورسا»
         يعرف ما يفعل: ينقل الكورسات أو يغلق الحساب. */ ?>
<?php if (!empty($blockers)): ?>
    <div class="tqa-note tqa-note--warn">
        <span aria-hidden="true"><?php echo tq_icon('shield', 18); ?></span>
        <span>
            <strong><?php echo t('هذا الحساب لا يحذف:'); ?></strong> <?php echo html_escape(implode(' · ', $blockers)); ?>.
            وحذفه يترك كورسا بلا صاحب وسجلا ماليا يقول «معلم #<?php echo (int) $row['id']; ?>»
            لا يعرف أحد من كان — والضرر في القراءة وهو لا يرجع.
            <strong><?php echo t('والإغلاق هو ما تريده غالبا'); ?></strong><?php echo t(': يمنع الدخول ولا يفقد شيئا.'); ?>
        </span>
    </div>
<?php else: ?>
    <div class="tqa-note">
        <span aria-hidden="true"><?php echo tq_icon('shield', 18); ?></span>
        <span>
            <?php echo t('هذا الحساب لم يبدأ بعد: بلا كورس ولا حصة ولا قيد مال — فالحذف متاح. ومتى نشر درسا أو دخل في قيد صار الإغلاق هو الباب.'); ?>
        </span>
    </div>
<?php endif; ?>
