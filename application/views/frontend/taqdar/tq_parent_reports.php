<?php
/**
 * بوابة ولي الأمر — التقارير.
 *
 * المرجع التصميمي: تطبيق البنك، لا لوحة تعليمية — كل شيء واضح ومفهوم من
 * نظرة واحدة وبلا مصطلحات. ولذلك التقرير هنا صفوف قليلة بعناوين بشرية:
 * «ما أنهاه» و«نتائجه» و«آخر نشاط» — لا «معدل تراكمي» ولا «مؤشر أداء».
 *
 * حاجز الرؤية نفسه مطبق: لا استعلام على محادثات المساعد الذكي، ولا على
 * منشورات المجتمع، ولا على إجابات الطالب المفردة — نعرض المتقن والمتبقي،
 * لا كل خطأ على حدة. «الرقابة الكاملة تنتج طالبا يخفي، لا طالبا يتعلم.»
 *
 * والدرجة المعروضة هنا هي **الدرجة التي يراها ابنك نفسه**، لا الدرجة
 * الخام: `Taqdar_marking_model::student_view()` هي الحكم الواحد —
 *   · المعتمدة تعرض بدرجة المعلم إن عدلها، لا بدرجة الآلة.
 *   · وما فيه سؤال مقالي لم يعتمد بعد لا يعرض لأحد.
 * وكان الحساب هنا يجمع `total_obtained_marks` الخام لكل محاولة مسلمة:
 * فيرى ولي الأمر رقما ولا يراه ابنه، أو يريان رقمين مختلفين للاختبار
 * الواحد — وأسرع طريق إلى شجار بينهما أن تعطيهما المنصة رقمين.
 *
 * و«الإتقان» عمود قائم لا منتظر: هدف متقن من هدف فتح له في كل مادة، من
 * `objectives` و`skill_state` — وكان التعليق هنا يقول إنه «ينتظر جدولا»
 * والجدولان ممتلئان.
 */

$tq_nav   = 'reports';
$tq_role  = 'parent';
$tq_title = t('التقارير');
$tq_sub   = t('كل مادة في سطر واحد');
$tq_icon  = 'chart';

$tq_uid = (int) $this->session->userdata('user_id');

$tq_ci = &get_instance();
$tq_ci->load->model('taqdar_parent_model');
$tq_ci->load->model('taqdar_marking_model');
$tq_mk = $tq_ci->taqdar_marking_model;

/* TQ-REPORTS-ONE — التقرير من `Taqdar_parent_model::reports()` لا من
   استعلامات في القالب. كانت الصفحة نسخة ثانية منه تقرأ «ما أنهاه» من رقم
   مخزن منحرف، و«نتائج الاختبارات» من النظام الموروث وحده، و«آخر نشاط» من
   المشاهدة وحدها — والتطبيق يقرأ النموذج فيرى أرقاما أخرى. والنموذج الآن
   يقرأ: التقدم بقاعدة القفل، والنتائج من النظامين مع امتحانات المحطات
   والواجبات المعتمدة، والإتقان لكل مادة. */
$tq_children = [];
foreach ($tq_ci->taqdar_parent_model->reports($tq_uid) as $tq_c) {
    $tq_subs = [];
    foreach ($tq_c['subjects'] as $tq_s) {
        $tq_s['avg_pct'] = $tq_s['avg_percent'] === null ? 0 : (int) $tq_s['avg_percent'];
        $tq_subs[] = $tq_s;
    }
    $tq_children[] = [
        'id'         => (int) $tq_c['student_id'],
        'first_name' => (string) $tq_c['name'],
        'last_name'  => '',
        'image'      => $tq_c['image'],
        'subjects'   => $tq_subs,
    ];
}

include 'portal_open.php';
?>

<div class="tq-cols">
    <div>
        <?php if ($tq_children): ?>

            <?php foreach ($tq_children as $tq_child): ?>
                <?php $tq_name = trim($tq_child['first_name'] . ' ' . $tq_child['last_name']); ?>
                <section class="tq-section" aria-labelledby="tq-child-<?php echo (int) $tq_child['id']; ?>">
                    <div class="tq-sectionhead">
                        <h2 id="tq-child-<?php echo (int) $tq_child['id']; ?>"><?php echo html_escape($tq_name); ?></h2>
                        <a class="tq-btn tq-btn--ghost tq-btn--sm"
                           href="<?php echo base_url('parent/child'); ?>?id=<?php echo (int) $tq_child['id']; ?>"><?php echo t('التفاصيل'); ?></a>
                    </div>

                    <?php if ($tq_child['subjects']): ?>
                        <div class="tq-card">
                            <div class="tq-table-wrap">
                                <table class="tq-table">
                                    <caption class="tq-sr"><?php echo t('مواد ____: ما أنهاه ونتائجه وآخر نشاط', array(html_escape($tq_name))); ?></caption>
                                    <thead>
                                        <tr>
                                            <th scope="col"><?php echo t('المادة'); ?></th>
                                            <th scope="col"><?php echo t('ما أنهاه'); ?></th>
                                            <th scope="col"><?php echo t('نتائج الاختبارات'); ?></th>
                                            <th scope="col"><?php echo t('الإتقان'); ?></th>
                                            <th scope="col"><?php echo t('آخر نشاط'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tq_child['subjects'] as $tq_s): ?>
                                            <tr>
                                                <td data-label="<?php echo te('المادة'); ?>">
                                                    <span class="tq-strong" style="color:var(--tq-navy)"><?php echo html_escape($tq_s['title']); ?></span>
                                                    <span class="tq-micro" style="display:block"><?php echo tq_iso(tq_lessons_word((int) $tq_s['lessons'])); ?></span>
                                                </td>
                                                <td data-label="<?php echo te('ما أنهاه'); ?>" style="min-inline-size:180px">
                                                    <?php echo tq_progress((int) $tq_s['progress'], t('ما أنهاه في ') . $tq_s['title']); ?>
                                                </td>
                                                <td data-label="<?php echo te('نتائج الاختبارات'); ?>">
                                                    <?php if ($tq_s['attempts'] > 0): ?>
                                                        <?php echo tq_num($tq_s['avg_pct'] . '%', 'tq-num--sm'); ?>
                                                        <span class="tq-micro" style="display:block">
                                                            <?php echo tq_iso(t('من ') . tq_exams_word((int) $tq_s['attempts'])); ?>
                                                        </span>
                                                    <?php elseif ($tq_s['held'] > 0): ?>
                                                        <span class="tq-caption"><?php echo t('ينتظر اعتماد معلمه'); ?></span>
                                                    <?php else: ?>
                                                        <span class="tq-caption"><?php echo t('لم يبدأ اختبارا'); ?></span>
                                                    <?php endif; ?>
                                                    <?php /* المحجوب يعلن لا يبتلع: من سلم اختبارا فيه سؤال
                                                             مقالي ينتظر تصحيحا، وصمت الشاشة عنه يقرأ إهمالا. */ ?>
                                                    <?php if ($tq_s['attempts'] > 0 && $tq_s['held'] > 0): ?>
                                                        <span class="tq-micro" style="display:block">
                                                            <?php echo tq_iso(t('و') . tq_exams_word((int) $tq_s['held'], t('لا اختبارات'), 'nom') . ((int) $tq_s['held'] === 1 ? t(' ينتظر') : t(' تنتظر')) . t(' اعتماد معلمه')); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="<?php echo te('الإتقان'); ?>">
                                                    <?php if (!empty($tq_s['mastery']['open'])): ?>
                                                        <?php echo tq_num((int) $tq_s['mastery']['percent'] . '%', 'tq-num--sm'); ?>
                                                        <span class="tq-micro" style="display:block">
                                                            <?php echo tq_iso(t('أتقن ') . (int) $tq_s['mastery']['mastered'] . t(' هدفا من ') . (int) $tq_s['mastery']['open']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="tq-caption"><?php echo t('لم يفتح هدفا مقاسا بعد'); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="<?php echo te('آخر نشاط'); ?>">
                                                    <?php echo (int) $tq_s['last_seen'] > 0
                                                        ? html_escape(tq_since((int) $tq_s['last_seen']))
                                                        : t('<span class="tq-caption">لم يبدأ بعد</span>'); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="tq-card tq-empty">
                            <span class="tq-icon-box tq-pastel--sand" style="color:var(--tq-sand-ink)" aria-hidden="true"><?php echo tq_icon('book', 24); ?></span>
                            <h3 class="tq-empty__title"><?php echo t('لا مواد مسجلة لـ'); ?><?php echo html_escape(explode(' ', $tq_name)[0]); ?></h3>
                            <p class="tq-empty__text"><?php echo t('حين يسجل في مادة يظهر تقريرها هنا في سطر واحد.'); ?></p>
                            <a class="tq-btn tq-btn--secondary" href="<?php echo base_url('parent/payments'); ?>"><?php echo t('المدفوعات'); ?></a>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>

        <?php else: ?>

            <div class="tq-card tq-empty">
                <span class="tq-icon-box tq-pastel--sky" style="color:var(--tq-sky-ink)" aria-hidden="true"><?php echo tq_icon('chart', 24); ?></span>
                <h2 class="tq-empty__title"><?php echo t('لا تقارير قبل ربط حساب ابنك'); ?></h2>
                <p class="tq-empty__text">
                    <?php echo t('بعد الربط تجد هنا كل مادة في سطر واحد: ما أنهاه منها، ونتائج اختباراته فيها، ومتى كان آخر نشاط له. بلا مصطلحات ولا جداول طويلة.'); ?>
                </p>
                <a class="tq-btn tq-btn--primary" href="<?php echo base_url('parent'); ?>"><?php echo t('اربط حساب ابنك'); ?></a>
            </div>

        <?php endif; ?>
    </div>

    <aside class="tq-aside">
        <div class="tq-pastel tq-pastel--sky">
            <span class="tq-pastel__label tq-micro"><?php echo t('كيف تقرأ التقرير'); ?></span>
            <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                <?php echo t('انظر إلى «آخر نشاط» أولا: الانقطاع يسبق تراجع النتائج دائما، ومعالجته أسهل.'); ?>
            </p>
        </div>

        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title"><?php echo t('التقرير الأسبوعي'); ?></h2></div>
            <p class="tq-caption">
                <?php echo t('إن أردت الخلاصة وحدها، يصلك كل أحد تقرير من أربعة أسطر تقرأ في عشر ثوان.'); ?>
            </p>
            <a class="tq-btn tq-btn--secondary tq-btn--block tq-btn--sm"
               href="<?php echo base_url('parent/weekly'); ?>"><?php echo t('عرض التقرير الأسبوعي'); ?></a>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
