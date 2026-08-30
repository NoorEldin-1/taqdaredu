<?php
/**
 * بوابة ولي الأمر — أبنائي.
 *
 * المرجع التصميمي لهذه البوابة كلها: تطبيق البنك، لا لوحة تعليمية.
 * كل شيء واضح ومفهوم من نظرة واحدة وبلا مصطلحات: أرقام قليلة كبيرة،
 * وجمل بلغة الناس، ولا «إتقان تراكمي» ولا «معدل محطة».
 *
 * الربط بين ولي الأمر وابنه في `parent_links` (ولي × ابن × حالة × تاريخ
 * موافقة). وصار يطلب من هذه الشاشة بدل أن ينشأ يدويا في القاعدة —
 * لكن الطلب يبدأ `pending` بلا تاريخ ولا يفتح شيئا، ولا يصير `active`
 * إلا بموافقة الابن من حسابه (أو الإدارة) وبتاريخ موثق في `consent_at`.
 * والتفعيل بلا تاريخ ممنوع في القاعدة نفسها لا في الشيفرة وحدها.
 *
 * ولا يخمن الربط بتشابه اسم أو بريد أو جوال — خطأ واحد هنا يعني فتح
 * بيانات طفل لغير أهله.
 */

$tq_ci = &get_instance();   // الطبقة المحملة داخل عرض لا تنسخ على $this، فتؤخذ من النسخة
$tq_ci->load->model('taqdar_parent_model');
$tq_pm = $tq_ci->taqdar_parent_model;
/* النماذج تنشر إلى `POST parent/children/link` في المتحكم، وهو يستدعي
   دوال هذا النموذج نفسها ويكتب سطر تدقيق. وهذا النداء يبقى شبكة أمان
   لأي نشر يصل إلى برنامج الشاشة نفسه — والمعالجة واحدة في الحالتين. */
$tq_pm->handle_post('children');

$tq_nav   = 'children';
$tq_role  = 'parent';
$tq_title = t('أبنائي');
$tq_sub   = t('صورة سريعة عن كل ابن، من نظرة واحدة');
$tq_icon  = 'users';

$tq_uid = (int) $this->session->userdata('user_id');

$tq_children = $tq_pm->children($tq_uid);
$tq_pending  = $tq_pm->links($tq_uid, 'pending');

/* لكل ابن: كورساته ومتوسط تقدمه وآخر نشاط — من الجداول الحقيقية. */
foreach ($tq_children as &$tq_child) {
    /* المتوسط على **كل** مواده لا على ما بدأه منها.
       `AVG()` تتخطى القيم الفارغة، و`LEFT JOIN` يعطي NULL لمادة بلا صف
       مشاهدة — أي لمادة لم يفتحها بعد. فمن سجل في مادتين وأنجز في واحدة
       ١٢٪ ولم يلمس الأخرى كان يقرأ وليه «١٢٪» بدل «٦٪»: المادة المهملة
       تختفي من الحساب بدل أن تخفضه، والرقم يتحسن كلما أهمل أكثر.
       والقسمة الصريحة على عدد المواد تعد غير المبدوءة صفرا كما هي. */
    $tq_row = $this->db->query(
        "SELECT COUNT(DISTINCT e.course_id) AS courses,
                COALESCE(SUM(COALESCE(w.course_progress, 0))
                         / NULLIF(COUNT(DISTINCT e.course_id), 0), 0) AS progress,
                COALESCE(MAX(w.date_updated), 0) AS last_seen
           FROM enrol e
           LEFT JOIN watch_histories w
                  ON w.student_id = e.user_id AND w.course_id = e.course_id
          WHERE e.user_id = ?",
        [(int) $tq_child['student_id']]
    )->row_array();

    $tq_child['courses']  = (int) ($tq_row['courses'] ?? 0);
    $tq_child['progress'] = (int) round((float) ($tq_row['progress'] ?? 0));
    $tq_child['last_seen'] = (int) ($tq_row['last_seen'] ?? 0);
    $tq_child['days'] = $tq_child['last_seen'] > 0
        ? max(0, (int) floor((time() - $tq_child['last_seen']) / 86400))
        : null;
}
unset($tq_child);

include 'portal_open.php';
?>

<?php echo $tq_pm->flash_html(); ?>

<div class="tq-cols">
    <div>
        <?php if ($tq_children): ?>

            <div class="tq-grid tq-grid--2 tq-stagger">
                <?php foreach ($tq_children as $tq_i => $tq_c): ?>
                    <?php
                    $tq_name = trim($tq_c['first_name'] . ' ' . $tq_c['last_name']);
                    $tq_ok   = $tq_c['days'] !== null && $tq_c['days'] <= 2;
                    $tq_sid  = (int) $tq_c['student_id'];
                    ?>
                    <article class="tq-card tq-card--float">
                        <div class="tq-row" style="gap:var(--tq-space-l)">
                            <img class="tq-avatar tq-avatar--lg"
                                 src="<?php echo tqs_person_img($tq_c['image']); ?>"
                                 alt="صورة <?php echo html_escape($tq_name); ?>">
                            <div style="flex:1;min-inline-size:0">
                                <h2 class="tq-h2" style="margin:0"><?php echo html_escape($tq_name); ?></h2>
                                <p class="tq-caption" style="margin:0">
                                    <?php /* «١ مواد» و«٢ مادة» خطآن ظاهران. والنعت يتبع
                                             منعوته: «مادتان مسجلتان» لا «مادتان مسجلة». */ ?>
                                    <?php echo tq_iso((int) $tq_c['courses'] === 2
                                        ? t('مادتان مسجلتان')
                                        : tq_count_units((int) $tq_c['courses'], t('مادة'), t('مادتان'), t('مادتين'),
                                            t('مواد'), t('مادة'), t('لا مواد'), 'nom', true) . t('مسجلة')); ?>
                                </p>
                            </div>
                            <?php echo $tq_ok
                                ? tq_badge('mastered', t('نشط'))
                                : tq_badge('due', $tq_c['days'] === null ? t('لم يبدأ بعد') : t('غاب') . TQ_LRI . $tq_c['days'] . TQ_PDI . t('يوما')); ?>
                        </div>

                        <div style="margin-block:var(--tq-space-xl)">
                            <p class="tq-caption" style="margin-block-end:var(--tq-space-s)"><?php echo t('أنهى من دروسه'); ?></p>
                            <?php echo tq_progress($tq_c['progress'], t('ما أنهاه') . $tq_name . t('من دروسه')); ?>
                        </div>

                        <a class="tq-btn tq-btn--primary tq-btn--block"
                           href="<?php echo base_url('parent/child'); ?>?id=<?php echo $tq_sid; ?>">
                            تفاصيل <?php echo html_escape(explode(' ', $tq_name)[0]); ?>
                        </a>

                        <p class="tq-micro" style="margin:var(--tq-space-m) 0 0">
                            ربط بموافقة <?php echo html_escape(explode(' ', $tq_name)[0]); ?> بتاريخ
                            <?php echo TQ_LRI . html_escape((string) $tq_c['consent_at']) . TQ_PDI; ?>
                        </p>

                        <form method="post" action="<?php echo base_url('parent/children/link'); ?>"
                              data-tq-confirm-title="إلغاء ربط <?php echo html_escape($tq_name); ?>؟"
                              data-tq-confirm="<?php echo te('لن ترى شيئا من بياناته بعدها: لا تقدمه ولا نتائجه ولا مدفوعاته.'); ?>"
                              data-tq-confirm-note="يبقى في السجل تاريخ موافقته وتاريخ الإلغاء. وإعادة المتابعة تحتاج طلبا جديدا وموافقة جديدة منه."
                              data-tq-confirm-ok="إلغاء الربط"
                              data-tq-confirm-tone="danger">
                            <?php echo tq_csrf(); ?>
                            <input type="hidden" name="tq_action" value="link_revoke">
                            <input type="hidden" name="student_id" value="<?php echo $tq_sid; ?>">
                            <button class="tq-btn tq-btn--ghost tq-btn--sm" type="submit"><?php echo t('إلغاء الربط'); ?></button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>

        <?php else: ?>

            <div class="tq-card tq-empty">
                <span class="tq-icon-box tq-pastel--sky" style="color:var(--tq-sky-ink)" aria-hidden="true"><?php echo tq_icon('users', 24); ?></span>
                <h2 class="tq-empty__title"><?php echo t('لا ابن مربوط بحسابك الآن'); ?></h2>
                <p class="tq-empty__text">
                    <?php echo t('بعد الربط ترى في هذه الصفحة صورة واحدة عن كل ابن: ماذا أنهى، وكيف يتقدم، ومتى كان آخر نشاط له. ولا تفتح لك بيانات أي طالب قبل أن يوافق هو على الربط.'); ?>
                </p>
            </div>

        <?php endif; ?>

        <!-- طلبات معلقة: أرسلت ولم يوافق عليها الابن بعد -->
        <?php if ($tq_pending): ?>
            <section class="tq-section" aria-labelledby="tq-pending-h" style="margin-block-start:var(--tq-space-xl)">
                <div class="tq-sectionhead">
                    <h2 id="tq-pending-h"><?php echo t('طلبات تنتظر موافقة أبنائك'); ?></h2>
                    <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_pending) . TQ_PDI; ?></span>
                </div>
                <div class="tq-card">
                    <?php /* `tq-prefrow` من مكتبة المكونات. كان الصف
                             `dl.tq-s-list > .tq-s-row` وهما صنفان يعرفهما
                             `tq_student_styles.php` وحده — وهذه الشاشة لا
                             تضمنه، فتعرض الصفوف بلا نمط بتة. */ ?>
                    <?php foreach ($tq_pending as $tq_p): ?>
                        <div class="tq-prefrow">
                            <span class="tq-prefrow__main">
                                <span class="tq-prefrow__title"><?php echo html_escape($tq_p['name']); ?></span>
                                <span class="tq-prefrow__hint" style="direction:ltr;text-align:start"><?php echo html_escape((string) $tq_p['email']); ?></span>
                            </span>
                            <span class="tq-prefrow__end">
                                <?php echo tq_badge('due', t('بانتظار موافقته')); ?>
                                <form method="post" action="<?php echo base_url('parent/children/link'); ?>"
                                      data-tq-confirm-title="سحب طلب ربط <?php echo html_escape($tq_p['name']); ?>؟"
                                      data-tq-confirm="<?php echo te('لن يصله الطلب بعد الآن، ولا يفتح شيء من بياناته — ولم يكن مفتوحا أصلا.'); ?>"
                                      data-tq-confirm-note="تستطيع إرسال طلب جديد إليه متى شئت."
                                      data-tq-confirm-ok="سحب الطلب">
                                    <?php echo tq_csrf(); ?>
                                    <input type="hidden" name="tq_action" value="link_cancel">
                                    <input type="hidden" name="link_id" value="<?php echo (int) $tq_p['id']; ?>">
                                    <button class="tq-btn tq-btn--ghost tq-btn--sm" type="submit"><?php echo t('سحب الطلب'); ?></button>
                                </form>
                            </span>
                        </div>
                    <?php endforeach; ?>
                    <p class="tq-micro" style="margin-block-start:var(--tq-space-m)">
                        <?php echo t('وصلت كل واحد منهم رسالة بنص الموافقة وإشعار في حسابه. ولا يفتح شيء من بياناته قبل أن يوافق هو.'); ?>
                    </p>
                </div>
            </section>
        <?php endif; ?>

        <!-- إضافة ابن -->
        <section class="tq-section" aria-labelledby="tq-add-h" style="margin-block-start:var(--tq-space-xl)">
            <div class="tq-sectionhead"><h2 id="tq-add-h"><?php echo t('إضافة ابن'); ?></h2></div>
            <div class="tq-card">
                <form method="post" action="<?php echo base_url('parent/children/link'); ?>">
                    <?php echo tq_csrf(); ?>
                    <input type="hidden" name="tq_action" value="link_request">
                    <div class="tq-field">
                        <label class="tq-field__label" for="tq-identifier"><?php echo t('بريد حساب ابنك في تقدر (أو رقم حسابه)'); ?></label>
                        <input class="tq-input tq-ltr" id="tq-identifier" name="identifier" type="text"
                               inputmode="email" required placeholder="name@example.com">
                        <p class="tq-field__hint">
                            <?php echo t('الربط يكون بحساب ابنك في المنصة نفسها. ولا نبحث بتشابه اسم أو جوال: خطأ واحد هنا يفتح بيانات طفل لغير أهله.'); ?>
                        </p>
                    </div>

                    <div class="tq-pastel tq-pastel--sand" style="margin-block:var(--tq-space-l)">
                        <span class="tq-pastel__label tq-micro"><?php echo t('نص الموافقة الذي يصل ابنك'); ?></span>
                        <p class="tq-pastel__body" style="margin:var(--tq-space-xs) 0 0">
                            <?php echo html_escape(Taqdar_parent_model::CONSENT_TEXT); ?>
                        </p>
                    </div>

                    <button class="tq-btn tq-btn--primary" type="submit"><?php echo t('إرسال طلب الربط'); ?></button>
                    <p class="tq-micro" style="margin-block-start:var(--tq-space-m)">
                        <?php echo t('ينشأ الطلب معلقا بلا تاريخ موافقة، ولا يفتح شيئا من بيانات ابنك. وتسجل الموافقة حين يوافق هو من حسابه، بتاريخها ونسخة نصها ومن أعطاها.'); ?>
                    </p>
                </form>
            </div>
        </section>
    </div>

    <aside class="tq-aside">
        <div class="tq-pastel tq-pastel--mint">
            <span class="tq-pastel__label tq-micro"><?php echo t('ما تراه هنا'); ?></span>
            <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                <?php echo t('تقدم ابنك ومواده وحصصه ومدفوعاته وملاحظات معلميه.'); ?>
            </p>
            <p class="tq-pastel__body tq-caption" style="margin:var(--tq-space-m) 0 0">
                <?php echo t('ولا نعرض محادثاته مع المساعد الذكي ولا منشوراته ولا كل إجابة خاطئة على حدة: الرقابة الكاملة تنتج طالبا يخفي، لا طالبا يتعلم.'); ?>
            </p>
        </div>

        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title"><?php echo t('التقرير الأسبوعي'); ?></h2></div>
            <p class="tq-caption">
                <?php echo t('يصلك صباح كل أحد: أربعة أسطر تقرأ في عشر ثوان، تخبرك بما تغير هذا الأسبوع.'); ?>
            </p>
            <a class="tq-btn tq-btn--secondary tq-btn--block tq-btn--sm"
               href="<?php echo base_url('parent/weekly'); ?>"><?php echo t('عرض التقرير'); ?></a>
        </div>

        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title"><?php echo t('قبل أن تلغي ربطا'); ?></h2></div>
            <p class="tq-caption">
                <?php echo t('إلغاء الربط يغلق بيانات الابن عنك في الحال، ويبقى في السجل تاريخ موافقته وتاريخ الإلغاء. وإن ألغيت ربط آخر ابن لك، خرجت من بوابة ولي الأمر حتى يوافق أحدهم على طلب جديد.'); ?>
            </p>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
