<?php
/**
 * بوابة ولي الأمر — الإشعارات.
 *
 * المرجع التصميمي: تطبيق البنك، لا لوحة تعليمية. والبنك لا يقاطعك بكل
 * حركة، يقاطعك بما يستدعي فعلا الآن.
 *
 * خمسة أحداث فقط تستحق المقاطعة الفورية:
 *   1. نتيجة امتحان
 *   2. رسوب في اختبار محطة
 *   3. انقطاع ثلاثة أيام
 *   4. طلب حصة خاصة
 *   5. شهادة جديدة
 * وما عداها ينتظر التقرير الأسبوعي. والفرز يقع في طبقة الاستعلام على
 * `notifications.type` بقائمة بيضاء، لا بإخفاء عناصر في الواجهة.
 *
 * ما ينتظر عملا على الخادم: هذه الأنواع الخمسة تكتب في `notifications.type`
 * عند وقوع الحدث (اليوم لا تكتب إلا أنواع أكاديمي العامة كـ signup،
 * ويضاف إليها `parent_link_request` من طبقة الربط). ولذلك يظهر أكثر ما
 * وصلك في «ينتظر التقرير الأسبوعي» — وهو الصواب، لا خطأ في العرض.
 *
 * وما اختاره ولي الأمر من هذه الأنواع صار مقروءا هنا من `parent_links.scope`:
 * النوع الموقوف يظهر موقوفا لا مخفيا، فمن أوقف شيئا يعرف أنه أوقفه
 * ولا يظن الشاشة معطوبة. والكتم نفسه يقع في `Taqdar_events_model` عند
 * الكتابة لا هنا عند العرض — إخفاء صف في شاشة ليس كتما.
 *
 * والمقروء يعلم من هنا. كانت الشاشة تقرأ ولا تكتب: يفتحها ولي الأمر
 * عشر مرات فيبقى جرس ترويسته يحمل الرقم نفسه إلى الأبد — شارة لا تنطفئ
 * تصير جزءا من الأثاث، فيضيع معها الإشعار الذي يستحق. وبوابة الطالب
 * تعلم المقروء منذ زمن، وهذه الفجوة بينهما لا مبرر لها.
 */

$tq_nav   = 'alerts';
$tq_role  = 'parent';
$tq_title = 'الإشعارات';
$tq_sub   = 'ما يستحق أن يقطع يومك — وما ينتظر الأحد';
$tq_icon  = 'bell';

$tq_uid = (int) $this->session->userdata('user_id');

/* «تحديد الكل كمقروء» فعل حقيقي، وينفذ قبل أي إخراج. */
if ($this->input->post('tq_action') === 'alerts_read_all') {
    $this->db->where('to_user', $tq_uid)->where('status', 0)
             ->update('notifications', ['status' => 1, 'updated_at' => (string) time()]);
    redirect(site_url('parent/alerts'), 'location', 303);
}

/* تفضيلات ولي الأمر: أي الأحداث اختار أن تصله (تحفظ في `parent_links.scope`).
   وتعرض هنا كما هي — فمن أوقف نوعا يرى أنه موقوف، لا يظنه معطوبا. */
$tq_ci = &get_instance();
$tq_ci->load->model('taqdar_parent_model');
$tq_prefs = $tq_ci->taqdar_parent_model->prefs($tq_uid);

/* القائمة البيضاء: الأحداث الخمسة وحدها تقاطع. */
$tq_urgent_types = [
    'exam_result'      => ['نتيجة امتحان',          'check-badge', 'mint'],
    'station_failed'   => ['رسوب في اختبار محطة',   'target',      'rose'],
    'inactivity_3days' => ['انقطاع ثلاثة أيام',     'clock',       'peach'],
    'session_request'  => ['طلب حصة خاصة',          'video',       'lilac'],
    'certificate'      => ['شهادة جديدة',           'award',       'sky'],
];

$tq_all = $this->db->query(
    "SELECT id, type, title, description, status, created_at
       FROM notifications
      WHERE to_user = ?
      ORDER BY created_at DESC
      LIMIT 60",
    [$tq_uid]
)->result_array();

$tq_urgent = [];
$tq_later  = [];
$tq_unread = 0;
foreach ($tq_all as $tq_n) {
    if ((int) $tq_n['status'] === 0) $tq_unread++;
    if (isset($tq_urgent_types[$tq_n['type']])) {
        $tq_urgent[] = $tq_n;
    } else {
        $tq_later[] = $tq_n;
    }
}

/* زر «تحديد الكل كمقروء» في رأس الصفحة لا مدفونا في الأسفل: هو الفعل
   الوحيد في هذه الشاشة، وموضعه حيث ينظر من فتحها ليطفئ جرسه. */
if ($tq_unread > 0) {
    $tq_tools = '<form method="post" action="' . base_url('parent/alerts') . '">'
              . tq_csrf()
              . '<input type="hidden" name="tq_action" value="alerts_read_all">'
              . '<button class="tq-btn tq-btn--secondary tq-btn--sm" type="submit">'
              . 'تحديد الكل كمقروء (' . TQ_LRI . $tq_unread . TQ_PDI . ')</button></form>';
}

include 'portal_open.php';
?>

<div class="tq-cols">
    <div>

        <!-- ما يستحق المقاطعة -->
        <section class="tq-section" aria-labelledby="tq-urgent-h">
            <div class="tq-sectionhead">
                <h2 id="tq-urgent-h">يستحق انتباهك الآن</h2>
                <?php if ($tq_urgent): ?>
                    <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_urgent) . TQ_PDI; ?></span>
                <?php endif; ?>
            </div>

            <?php if ($tq_urgent): ?>
                <div class="tq-card">
                    <ul class="tq-stack">
                        <?php foreach ($tq_urgent as $tq_n): ?>
                            <?php [$tq_label, $tq_ic, $tq_fam] = $tq_urgent_types[$tq_n['type']]; ?>
                            <li class="tq-row" style="gap:var(--tq-space-l);padding-block:var(--tq-space-m);border-block-end:1px solid var(--tq-line)">
                                <span class="tq-icon-box tq-pastel--<?php echo $tq_fam; ?>"
                                      style="color:var(--tq-<?php echo $tq_fam; ?>-ink)" aria-hidden="true">
                                    <?php echo tq_icon($tq_ic); ?>
                                </span>
                                <div style="flex:1;min-inline-size:0">
                                    <p class="tq-strong" style="margin:0;color:var(--tq-navy)"><?php echo html_escape($tq_n['title']); ?></p>
                                    <?php /* الجسم كان يقرأ من القاعدة ولا يعرض: عنوان بلا نص
                                             يقول «وقع شيء» ولا يقول ماذا، فيفتح ولي الأمر
                                             شاشة أخرى ليعرف ما كان سطرا هنا. */ ?>
                                    <?php if (trim((string) $tq_n['description']) !== ''): ?>
                                        <p class="tq-caption" style="margin:var(--tq-space-xs) 0 0">
                                            <?php echo tq_iso(html_escape($tq_n['description'])); ?>
                                        </p>
                                    <?php endif; ?>
                                    <p class="tq-micro" style="margin:var(--tq-space-xs) 0 0"><?php echo html_escape($tq_label); ?></p>
                                </div>
                                <span class="tq-micro"><?php echo html_escape(tq_since((int) $tq_n['created_at'])); ?></span>
                                <?php if (empty($tq_prefs[$tq_n['type']])): ?>
                                    <?php echo tq_badge('idle', 'نوع أوقفته'); ?>
                                <?php endif; ?>
                                <?php if ((int) $tq_n['status'] === 0): ?>
                                    <?php echo tq_badge('due', 'جديد'); ?>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <div class="tq-card tq-empty">
                    <span class="tq-icon-box tq-pastel--mint" style="color:var(--tq-mint-ink)" aria-hidden="true"><?php echo tq_icon('bell', 24); ?></span>
                    <h3 class="tq-empty__title">لا شيء يستدعي المقاطعة</h3>
                    <p class="tq-empty__text">
                        لا نقاطعك إلا بخمسة أحداث: نتيجة امتحان، أو رسوب في اختبار محطة،
                        أو انقطاع ثلاثة أيام، أو طلب حصة خاصة، أو شهادة جديدة.
                    </p>
                    <a class="tq-btn tq-btn--secondary" href="<?php echo base_url('parent/weekly'); ?>">التقرير الأسبوعي</a>
                </div>
            <?php endif; ?>
        </section>

        <!-- ما ينتظر التقرير الأسبوعي -->
        <section aria-labelledby="tq-later-h">
            <div class="tq-sectionhead">
                <h2 id="tq-later-h">ينتظر التقرير الأسبوعي</h2>
                <?php if ($tq_later): ?>
                    <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_later) . TQ_PDI; ?></span>
                <?php endif; ?>
            </div>

            <?php if ($tq_later): ?>
                <div class="tq-card">
                    <p class="tq-caption" style="margin-block-end:var(--tq-space-l)">
                        هذه أحداث مفيدة لكنها لا تستدعي أن تقطع يومك. تصلك مجمعة صباح الأحد.
                    </p>
                    <ul class="tq-stack">
                        <?php foreach ($tq_later as $tq_n): ?>
                            <li class="tq-row" style="gap:var(--tq-space-l);padding-block:var(--tq-space-s)">
                                <span class="tq-icon-box" style="background:var(--tq-ground);color:var(--tq-text2)" aria-hidden="true">
                                    <?php echo tq_icon('bell'); ?>
                                </span>
                                <span style="flex:1;min-inline-size:0">
                                    <span class="tq-strong" style="display:block;color:var(--tq-navy)"><?php echo html_escape($tq_n['title']); ?></span>
                                </span>
                                <span class="tq-micro"><?php echo html_escape(tq_since((int) $tq_n['created_at'])); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <div class="tq-card tq-empty">
                    <span class="tq-icon-box tq-pastel--sand" style="color:var(--tq-sand-ink)" aria-hidden="true"><?php echo tq_icon('clipboard', 24); ?></span>
                    <h3 class="tq-empty__title">لا شيء مؤجل</h3>
                    <p class="tq-empty__text">كل ما لا يستحق المقاطعة يجمع هنا ويصلك في تقرير الأحد.</p>
                    <a class="tq-btn tq-btn--secondary" href="<?php echo base_url('parent'); ?>">أبنائي</a>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <aside class="tq-aside">
        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title">ما الذي يقاطعك</h2></div>
            <ul class="tq-stack">
                <?php foreach ($tq_urgent_types as $tq_key => $tq_t): ?>
                    <?php $tq_on = !empty($tq_prefs[$tq_key]); ?>
                    <li class="tq-row" style="gap:var(--tq-space-m)<?php echo $tq_on ? '' : ';opacity:.55'; ?>">
                        <span class="tq-icon-box tq-pastel--<?php echo $tq_t[2]; ?>"
                              style="color:var(--tq-<?php echo $tq_t[2]; ?>-ink)" aria-hidden="true">
                            <?php echo tq_icon($tq_t[1]); ?>
                        </span>
                        <span class="tq-strong" style="flex:1;color:var(--tq-navy)"><?php echo html_escape($tq_t[0]); ?></span>
                        <?php echo $tq_on ? tq_badge('mastered', 'يصلك') : tq_badge('idle', 'أوقفته'); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="tq-micro" style="margin-block-start:var(--tq-space-l)">
                خمسة أحداث لا سادس لها. ما عداها ينتظر تقرير الأحد
                <?php echo !empty($tq_prefs['weekly']) ? '(وهو مفعل عندك)' : '(وقد أوقفته أنت)'; ?>.
                <a href="<?php echo base_url('parent/settings'); ?>">غير ما يصلك</a>.
            </p>
        </div>

        <div class="tq-pastel tq-pastel--lilac">
            <span class="tq-pastel__label tq-micro">لماذا نقلل الإشعارات</span>
            <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                الإشعار الذي يتكرر بلا سبب يغلق كله، فيضيع معه الإشعار المهم.
                نقاطعك قليلا لتنتبه حين نقاطعك.
            </p>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
