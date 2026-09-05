<?php
/**
 * بوابة المعلم — كتبي (TQ-BOOK).
 *
 * ═══ ما كان ═══
 *
 * المعلم يرفع الدرس والاختبار والكورس، **وكتابه لا باب له**: يؤلفه
 * ويرسله بالبريد ليضعه المسؤول بيده في `taqdar_admin/module/books`، أو
 * لا يصل. ولا شاشة تقول له أين وصل، ولا رقم يقول كم بيع، ولا سطر يقول
 * كم ناله منه.
 *
 * ═══ والقاعدة في الطبقة لا هنا ═══
 *
 * الشاشة تعرض ولا تحكم: الحقول من `Taqdar_book_model::book_fields()`،
 * والحفظ من `save_book()`، وحال النشر من `may_publish()`. ونسخة ثانية
 * من قواعدها في قالب تجعل شاشة المعلم تقبل ما ترده اللوحة.
 *
 * ═══ وثلاثة أرقام يقرؤها صاحبها ═══
 *
 * أين كتابه الآن (الحالة)، وكم بيع، وكم ناله. والسعر والنسبة **قرار
 * إدارة** — المعلم لا يسعر محتوى المنصة — لكن قرارا لا يعلمه صاحبه ليس
 * شفافية: من يقرأ قيدا في دفتره ولا يعرف من أين جاء يسأل الدعم، أو يظن
 * أن المنصة أخذت أكثر مما أخذت. وهو حكم `tq_teacher_courses` نفسه.
 */

/* `tq_s_empty()` تعرف في `tq_student_styles.php` وهو يطبع كتلة
   `<style>` عند ضمه، فيضم **قبل** أي إخراج — كما تفعل
   `tq_teacher_analytics.php` بالضبط. */
include 'tq_student_styles.php';

$tq_nav   = 'books';
$tq_role  = 'teacher';
$tq_title = t('كتبي');
$tq_sub   = t('كتبك التي تعرض في المنصة — تؤلفها هنا وتراجعها الإدارة قبل نشرها.');
$tq_icon  = 'book';

$tq_uid = (int) $this->session->userdata('user_id');

$tq_ci = &get_instance();
$tq_ci->load->model('taqdar_book_model', 'tq_bk');
$tq_ci->load->model('taqdar_wallet_model', 'tq_wal');

$tq_books  = $tq_ci->tq_bk->books_of($tq_uid);
$tq_cfg    = $tq_ci->tq_bk->config();
$tq_states = Taqdar_book_model::statuses();

/* ما ناله من بيع كتبه — من **الدفتر** لا من `subscriptions`: الدفتر هو
   ما يصرف منه، ورقمان يفترقان في شاشتين يجعلان المعلم يسأل عن الفرق
   ولا أحد يعرف أيهما الصحيح. */
$tq_earn = array('n' => 0, 'net' => 0);
try { $tq_earn = $tq_ci->tq_wal->book_sale_earnings($tq_uid); } catch (Throwable $e) {}

$tq_sold = 0;
foreach ($tq_books as $tq_b) $tq_sold += (int) $tq_b['sales'];

/* الحالة إلى شارة — بألوان بوابة الطالب نفسها لا بألوان اللوحة. */
$tq_badge = array(
    'published' => array('mastered', t('منشور')),
    'review'    => array('due',      t('بانتظار المراجعة')),
    'draft'     => array('idle',     t('مسودة')),
    'rejected'  => array('late',     t('مرفوض')),
);

include 'portal_open.php';
?>

<div class="tq-cols">
    <div>

        <?php if ($m = tq_flash('tq_ok')): ?>
            <div class="tq-alert tq-alert--ok tq-section" role="status"><?php echo html_escape($m); ?></div>
        <?php endif; ?>
        <?php if ($m = tq_flash('tq_error')): ?>
            <div class="tq-alert tq-alert--no tq-section" role="alert"><?php echo html_escape($m); ?></div>
        <?php endif; ?>

        <div class="tq-card tq-card--panel tq-section">
            <div class="tq-row tq-row--between" style="flex-wrap:wrap;gap:var(--tq-space-m)">
                <div>
                    <h2 class="tq-card__title" style="margin:0"><?php echo t('أضف كتابا'); ?></h2>
                    <p class="tq-caption" style="margin-block-start:var(--tq-space-xs)">
                        <?php if ($tq_cfg['direct_publish']): ?>
                            <?php echo t('ارفع ملف PDF وعرفه، وينشر مباشرة لطلابك.'); ?>
                        <?php else: ?>
                            <?php echo t('ارفع ملف PDF وعرفه، ثم أعلنه «منشورا» فيصل إلى مراجعة الإدارة.'); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <a class="tq-btn tq-btn--primary" href="<?php echo base_url('teacher/books/new'); ?>">
                    <?php echo tq_icon('plus', 16); ?> <?php echo t('كتاب جديد'); ?>
                </a>
            </div>
        </div>

        <?php if ($tq_books): ?>
            <div class="tq-card">
                <div class="tq-table-wrap">
                    <table class="tq-table">
                        <caption class="tq-sr"><?php echo t('كتبك: الحالة وعدد الصفحات وما بيع منها'); ?></caption>
                        <thead>
                            <tr>
                                <th scope="col"><?php echo t('الكتاب'); ?></th>
                                <th scope="col"><?php echo t('الحالة'); ?></th>
                                <th scope="col"><?php echo t('الصفحات'); ?></th>
                                <th scope="col"><?php echo t('أين يظهر'); ?></th>
                                <th scope="col"><?php echo t('البيع المفرد'); ?></th>
                                <th scope="col"><span class="tq-sr"><?php echo t('إجراءات'); ?></span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tq_books as $tq_b):
                                $tq_st = (string) $tq_b['status'];
                                list($tq_kind, $tq_lbl) = isset($tq_badge[$tq_st])
                                    ? $tq_badge[$tq_st] : array('idle', $tq_st);
                                $tq_o = $tq_b['offer'];
                            ?>
                                <tr>
                                    <td data-label="<?php echo te('الكتاب'); ?>">
                                        <span class="tq-strong" style="color:var(--tq-navy)"><?php echo html_escape($tq_b['title']); ?></span>
                                        <?php if (trim((string) $tq_b['subject']) !== ''): ?>
                                            <span class="tq-micro" style="display:block"><?php echo html_escape($tq_b['subject']); ?></span>
                                        <?php endif; ?>
                                    </td>

                                    <td data-label="<?php echo te('الحالة'); ?>">
                                        <?php echo tq_badge($tq_kind, $tq_lbl); ?>
                                        <?php /* **الرفض يقول سببه في مكانه**: بلاه يعيد
                                                 المعلم إرسال الكتاب كما هو، وتدور الدورة. */ ?>
                                        <?php if ($tq_st === 'rejected' && trim((string) $tq_b['tq_review_note']) !== ''): ?>
                                            <span class="tq-micro" style="display:block;color:var(--tq-red-ink)">
                                                <?php echo html_escape($tq_b['tq_review_note']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td data-label="<?php echo te('الصفحات'); ?>">
                                        <?php if (trim((string) $tq_b['file']) === ''): ?>
                                            <span class="tq-micro"><?php echo t('لا ملف'); ?></span>
                                        <?php else: ?>
                                            <?php echo tq_num((int) $tq_b['pages'], 'tq-num--sm'); ?>
                                        <?php endif; ?>
                                    </td>

                                    <?php /* TQ-BOOK-GRADE — الصف هو ما يدخله في الباقة،
                                             والمرحلة ترشحه في الكتالوج. وكتاب بلا صف
                                             ينشر ولا يصل إلى مشترك واحد — فيقال هنا
                                             حيث يقرؤه صاحبه لا في اللوحة وحدها. */ ?>
                                    <td data-label="<?php echo te('أين يظهر'); ?>">
                                        <?php if (trim((string) $tq_b['cat_name']) !== ''): ?>
                                            <span class="tq-micro" style="display:block"><?php echo html_escape($tq_b['cat_name']); ?></span>
                                        <?php endif; ?>
                                        <?php if ((int) $tq_b['grade_id'] > 0): ?>
                                            <span class="tq-micro" style="display:block;color:var(--tq-mint-ink)">
                                                <?php echo t('في باقات'); ?> <?php echo html_escape($tq_b['grade_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="tq-micro" style="display:block;color:var(--tq-text3)">
                                                <?php echo t('بلا صف — لا تفتحه باقة'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <?php /* السعر والنسبة قرار إدارة، ويقرؤهما صاحبهما.
                                             والصمت هنا يجعله يسأل الدعم عن أول قيد. */ ?>
                                    <td data-label="<?php echo te('البيع المفرد'); ?>">
                                        <?php if (!empty($tq_o['marked']) && (int) $tq_o['price'] > 0): ?>
                                            <span class="tq-strong tq-ltr"><?php echo number_format(((int) $tq_o['price']) / 100, 2); ?></span>
                                            <span class="tq-micro"><?php echo t('ر.س'); ?></span>
                                            <span class="tq-micro" style="display:block">
                                                <?php echo t('نصيبك'); ?>
                                                <span class="tq-ltr"><?php echo number_format(((int) $tq_o['share']) / 100, 2); ?></span>
                                                (<span class="tq-ltr"><?php echo rtrim(rtrim(number_format((float) $tq_o['percent'], 2), '0'), '.'); ?>%</span>)
                                            </span>
                                            <?php if ((int) $tq_b['sales'] > 0): ?>
                                                <span class="tq-micro" style="display:block;color:var(--tq-mint-ink)">
                                                    <?php echo t('بيع'); ?> <?php echo tq_num((int) $tq_b['sales']); ?> <?php echo t('مرة'); ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="tq-micro"><?php echo t('مجاني — يقرأ بلا ثمن'); ?></span>
                                        <?php endif; ?>
                                    </td>

                                    <td data-label="<?php echo te('إجراءات'); ?>">
                                        <a class="tq-btn tq-btn--ghost tq-btn--sm"
                                           href="<?php echo base_url('teacher/books/' . (int) $tq_b['id']); ?>"><?php echo t('حرر'); ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="tq-card">
                <?php echo tq_s_empty('book', 'sand', t('لم تضف كتابا بعد'),
                      t('الكتاب يعرض المادة، والبرنامج يشرحها. وكتابك يقرؤه طلابك من مكتبتهم صفحة صفحة.'),
                      t('كتاب جديد'), base_url('teacher/books/new'), false, 'primary'); ?>
            </div>
        <?php endif; ?>

    </div>

    <aside>
        <div class="tq-card tq-section">
            <h2 class="tq-card__title"><?php echo t('كتبك بالأرقام'); ?></h2>
            <table class="tq-table">
                <tbody>
                    <tr>
                        <th scope="row"><?php echo t('كتبك'); ?></th>
                        <td><?php echo tq_num(count($tq_books), 'tq-num--sm'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo t('بيع مفردا'); ?></th>
                        <td><?php echo tq_num($tq_sold, 'tq-num--sm'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo t('صافي ما نالك'); ?></th>
                        <td><?php echo tq_num(number_format(((int) $tq_earn['net']) / 100, 2)
                                              . ' ' . t('ر.س'), 'tq-num--sm'); ?></td>
                    </tr>
                </tbody>
            </table>
            <p class="tq-caption">
                <?php echo t('والرقم من دفترك لا من جدول المبيعات — وهو ما يصرف منه.'); ?>
                <a href="<?php echo base_url('teacher/wallet'); ?>"><?php echo t('افتح محفظتك'); ?></a>
            </p>
        </div>

        <?php /* ما يقرره غيره يقرأ هنا لا يخمن: من لا يعرف أن الصف قرار
                 إدارة يظن أن كتابه لم يدخل الباقة لأنه أخطأ. */ ?>
        <div class="tq-card">
            <h2 class="tq-card__title"><?php echo t('كيف يصل كتابك'); ?></h2>
            <ol class="tq-caption" style="padding-inline-start:1.2em;line-height:1.9">
                <li><?php echo t('ترفع ملف PDF وتكتب عنوانه ومادته ووصفه.'); ?></li>
                <li>
                    <?php if ($tq_cfg['direct_publish']): ?>
                        <?php echo t('تعلنه «منشورا» فيظهر لطلابك مباشرة.'); ?>
                    <?php else: ?>
                        <?php echo t('تعلنه «منشورا» فيصل إلى مراجعة الإدارة، وتخبرك بقرارها.'); ?>
                    <?php endif; ?>
                </li>
                <li><?php echo t('تسنده الإدارة إلى صف، فتفتحه باقات ذلك الصف لمشتركيها ويحسب لك وزنه في وعائها.'); ?></li>
                <li><?php echo t('وإن سعرته الإدارة للبيع المفرد، يقيد نصيبك في دفترك عند كل بيعة.'); ?></li>
            </ol>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
