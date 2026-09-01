<?php
/**
 * الإشعارات — بوابة الطالب.
 *
 * الإشعارات مجموعة زمنيا لا مرتبة سردا واحدا: «اليوم» و«أمس» و«قبل ٣ أيام»
 * تجيب سؤال الطالب الحقيقي — ما الذي فاتني؟ — بلا أن يقرأ تاريخا في كل صف.
 *
 * كل ما في هذه الشاشة من جدول notifications الحقيقي، وتفضيلات الإشعارات
 * تقرأ من notification_settings (إعداد المنصة لدور الطالب). وما لا يملك
 * الطالب تجاوزا خاصا به بعد يقال صراحة لا يزين بمفتاح لا يحفظ شيئا.
 *
 * ومصدر أحداث تقدر هنا واحد: `Taqdar_events_model::notify()` — يكتبها
 * الكرون `taqdar_cron_events` دوريا، ويكتبها مطلقو الأحداث اللحظية عند
 * وقوعها. والشاشة لا تعرف أيهما كتب: هي تقرأ الجدول لا المصدر.
 */

$tq_nav   = 'notifications';
$tq_role  = 'student';
$tq_title = t('الإشعارات');
$tq_sub   = t('تابع كل ما يهمك في رحلتك التعليمية');
$tq_icon  = 'bell';

$uid = (int) $this->session->userdata('user_id');

/* ---- «تحديد الكل كمقروء» فعل حقيقي، وينفذ قبل أي إخراج ------------- */
/* القرار كله في `Taqdar_student_model`: التصنيف والعدادات والقراءة.
   والواجهة (`Api_v1`) تسأل الأسئلة نفسها وتنادي الدوال نفسها، فلا
   يقرأ الطالب في التطبيق «تنبيهات أخرى» وفي الموقع «رسوب في اختبار
   محطة» عن الحدث الواحد. */
$this->load->model('taqdar_student_model', 'tq_stu');

/* ---- «تحديد الكل كمقروء» فعل حقيقي، وينفذ قبل أي إخراج ------------- */
if ($this->input->post('action') === 'mark_all_read') {
    $this->tq_stu->mark_all_notifications_read($uid);
    redirect(site_url('student/notifications'), 'location', 302);
}

/* ---- «هذا الإشعار وحده» ---------------------------------------------
 *
 * TQ-NOTIF-READ — كان الصف `div` لا يستقبل ضغطة، فلا سبيل إلى قراءة
 * إشعار بعينه: إما «تحديد الكل كمقروء» وإما تبقى النقطة الزرقاء على
 * تسعة إشعارات قرئت كلها. ومن قرأ واحدا ثم عاد وجد العداد كما تركه،
 * فظن الشاشة لا تحفظ شيئا.
 *
 * والحال يعاد كما كان: من كان يصفي «غير المقروءة» يبقى عليها بعد
 * القراءة، ولا يقذف إلى «الكل» فيفقد موضعه من القائمة. */
if ($this->input->post('action') === 'mark_read') {
    $this->tq_stu->mark_notification_read($uid, (int) $this->input->post('id'));
    $tq_back = $this->input->post('state', true);
    $tq_back = in_array($tq_back, ['unread', 'read'], true) ? '?state=' . $tq_back : '';
    redirect(site_url('student/notifications') . $tq_back, 'location', 302);
}

/* ---- الإشعارات ------------------------------------------------------- */
$tq_state = $this->input->get('state', true);
$tq_state = in_array($tq_state, ['unread', 'read'], true) ? $tq_state : 'all';

$tq_feed = $this->tq_stu->notifications($uid, $tq_state);
$tq_list = $tq_feed['items'];

$tq_unread_count = (int) $tq_feed['counts']['unread'];
$tq_read_count   = (int) $tq_feed['counts']['read'];
$tq_by_kind      = $tq_feed['by_kind'];

/* الكل — يقرأ للمجموعات الزمنية ولعداد الأسبوع، والتصفية وقعت أعلاه. */
$tq_all = $this->tq_stu->notifications($uid, 'all')['items'];

/* `get_instance()` لا `$this` داخل المغلقة: `$this` في العرض هو المحمل
   لا المتحكم، وربطه في مغلقة يعتمد على سحر `__get` بلا داع. */
$tq_kind = static function ($type) {
    return get_instance()->tq_stu->notification_kind($type);
};

/* ---- المجموعات الزمنية ----------------------------------------------- */
$tq_midnight = strtotime('today');
$tq_groups   = ['اليوم' => [], 'أمس' => [], 'قبل 3 أيام' => [], 'أقدم' => []];
foreach ($tq_list as $n) {
    $ts  = (int) $n['created_at'];
    $age = (int) floor(($tq_midnight - strtotime(date('Y-m-d', $ts))) / 86400);
    if ($age <= 0)      { $tq_groups['اليوم'][] = $n; }
    elseif ($age === 1) { $tq_groups['أمس'][] = $n; }
    elseif ($age <= 3)  { $tq_groups['قبل 3 أيام'][] = $n; }
    else                { $tq_groups['أقدم'][] = $n; }
}

$tq_today_count = count($tq_groups['اليوم']);
$tq_week_count  = 0;
foreach ($tq_all as $n) {
    if ((int) $n['created_at'] >= $tq_midnight - 6 * 86400) {
        $tq_week_count++;
    }
}

/* ---- تفضيلات الإشعارات المتاحة لدور الطالب --------------------------- */
$tq_prefs = [];
foreach ($this->db->get('notification_settings')->result_array() as $s) {
    $types = json_decode((string) $s['user_types'], true);
    if (!is_array($types) || !in_array('student', $types, true)) {
        continue;
    }
    $sys   = json_decode((string) $s['system_notification'], true);
    $mail  = json_decode((string) $s['email_notification'], true);
    [$label] = $tq_kind($s['type']);
    $tq_prefs[] = [
        'label'  => $label,
        'title'  => $s['setting_title'],
        'system' => (int) ($sys['student'] ?? 0) === 1,
        'email'  => (int) ($mail['student'] ?? 0) === 1,
    ];
}

$tq_states = [
    'all'    => [t('الكل'), count($tq_all)],
    'unread' => [t('غير المقروءة'), $tq_unread_count],
    'read'   => [t('المقروءة'), $tq_read_count],
];

include 'portal_open.php';
include 'tq_notif_styles.php';
?>


<div class="tq-cols">
    <div>

        <?php /* TQ-SPAM — هذه الشاشة سجل ما وصل داخل المنصة، ونسخة كل صف
                 منها ترسل بالبريد كذلك. فمن يقرأ هنا خبرا لم يصله بريده
                 يقف على الفرق بعينه — وهذا موضعه.
                 وفي العمود الرئيسي لا في الجانبي: الجانبي ينتقل تحت
                 القائمة كلها دون 1024 بكسل، فيقرؤه من نزل مئة إشعار. */ ?>
        <?php echo tq_spam_notice(array('what' => t('إشعاراتنا'), 'class' => 'tq-spam--top')); ?>

        <div class="tq-row tq-row--between" style="margin-block-end:var(--tq-space-l);flex-wrap:wrap;gap:var(--tq-space-m)">
            <nav class="tq-tabs" aria-label="<?php echo te('تصفية الإشعارات'); ?>" style="margin-block-end:0;border-block-end:0">
                <?php foreach ($tq_states as $key => $info): ?>
                    <a class="tq-tab"
                       href="<?php echo base_url('student/notifications') . ($key === 'all' ? '' : '?state=' . $key); ?>"
                       <?php echo tq_active($key, $tq_state); ?>>
                        <?php echo html_escape($info[0]); ?>
                        <span class="tq-tab__n"><?php echo TQ_LRI . (int) $info[1] . TQ_PDI; ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php if ($tq_unread_count > 0): ?>
                <form method="post" action="<?php echo base_url('student/notifications'); ?>">
                    <?php /* TQ-CSRF — كان ينقص، و`csrf_protection` مفعل ولا استثناء
                             لـ`student/notifications`. فكان الزر يرد بـ403 «الإجراء
                             غير مسموح» على شاشة كاملة، لا برسالة في الشاشة — ويقرأ
                             ذلك على أنه «الإشعارات لا تقرأ». ونظيره في بوابة المعلم
                             كان يحمله، فالشاشتان تفترقان في السطر الوحيد الذي يهم. */ ?>
                    <?php echo tq_csrf(); ?>
                    <button class="tq-btn tq-btn--ghost tq-btn--sm" type="submit" name="action" value="mark_all_read">
                        <span aria-hidden="true"><?php echo tq_icon('check', 16); ?></span>
                        <?php echo t('تحديد الكل كمقروء'); ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (!$tq_list): ?>
            <div class="tq-card tq-card--panel">
                <div class="tq-empty">
                    <div class="tq-empty__art tq-pastel tq-pastel--sky" style="display:grid;place-items:center;border-radius:var(--tq-radius-pill)">
                        <span class="tq-pastel__icon" aria-hidden="true"><?php echo tq_icon('bell', 44); ?></span>
                    </div>
                    <h2 class="tq-empty__title">
                        <?php echo $tq_state === 'unread' ? t('لا إشعارات غير مقروءة') : ($tq_state === 'read' ? t('لا إشعارات مقروءة') : t('لا إشعارات بعد')); ?>
                    </h2>
                    <p class="tq-empty__text">
                        <?php echo t('هنا يصلك كل جديد: درس نشر في مادتك، وموعد حصة يقترب، وتقييم واجب، وشهادة أتممتها — مجموعا بيومه لا مبعثرا.'); ?>
                    </p>
                    <a class="tq-btn tq-btn--primary" href="<?php echo base_url('student/lessons'); ?>"><?php echo t('اذهب إلى دروسي'); ?></a>
                </div>
            </div>
        <?php else: ?>
            <?php /* نموذج واحد يلف القائمة كلها، وكل صف زر يرسل معرفه في
                     `name="id"` — فلا نموذج لكل إشعار ولا سطر جافاسكربت.
                     و`state` يرافقه ليعود المصفي إلى تصفيته. */ ?>
            <form method="post" action="<?php echo base_url('student/notifications'); ?>">
                <?php echo tq_csrf(); ?>
                <input type="hidden" name="action" value="mark_read">
                <input type="hidden" name="state" value="<?php echo html_escape($tq_state); ?>">
            <?php foreach ($tq_groups as $label => $items): ?>
                <?php if (!$items) { continue; } ?>
                <section class="tq-daygroup" aria-labelledby="tq-g-<?php echo md5($label); ?>">
                    <span class="tq-daygroup__label" id="tq-g-<?php echo md5($label); ?>"><?php echo te($label); ?></span>
                    <div class="tq-card tq-card--panel" style="padding:var(--tq-space-s)">
                        <?php foreach ($items as $n): ?>
                            <?php
                            [$kind_label, $kind_icon, $kind_tone] = $tq_kind($n['type']);
                            $unread = ((int) $n['status'] === 0);
                            $text   = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $n['description'])));
                            $ts     = (int) $n['created_at'];
                            ?>
                            <?php /* غير المقروء زر يقرأ نفسه، والمقروء صف نص —
                                     ولا يعرض زرا لا يفعل شيئا. */ ?>
                            <?php $tag = $unread ? 'button' : 'div'; ?>
                            <<?php echo $tag; ?> class="tq-notif<?php echo $unread ? ' tq-notif--unread' : ''; ?>"
                                <?php if ($unread): ?>type="submit" name="id" value="<?php echo (int) $n['id']; ?>"<?php endif; ?>>
                                <span class="tq-notif__dot" aria-hidden="true"></span>
                                <span>
                                    <span class="tq-notif__title"><?php echo tq_iso(html_escape($n['title'])); ?></span>
                                    <span class="tq-notif__line"><?php echo tq_iso(html_escape(mb_substr($text, 0, 90))); ?></span>
                                    <span class="tq-notif__line"><?php echo html_escape($kind_label); ?></span>
                                    <?php if ($unread): ?><span class="tq-sr"><?php echo t('غير مقروء — اضغط لتحديده كمقروء'); ?></span><?php endif; ?>
                                </span>
                                <span class="tq-notif__time">
                                    <?php echo tq_num(date('g:i', $ts), 'tq-num--sm'); ?> <?php echo (int) date('G', $ts) < 12 ? t('ص') : t('م'); ?>
                                </span>
                                <span class="tq-icon-box tq-pastel--<?php echo $kind_tone; ?>" aria-hidden="true">
                                    <?php echo tq_icon($kind_icon); ?>
                                </span>
                            </<?php echo $tag; ?>>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
            </form>
        <?php endif; ?>

    </div>

    <aside class="tq-aside">

        <!-- تصفية بالنوع بعدد لكل نوع -->
        <section class="tq-card tq-card--panel" aria-labelledby="tq-kinds-h">
            <div class="tq-card__head"><h2 class="tq-card__title" id="tq-kinds-h"><?php echo t('تصفية الإشعارات'); ?></h2></div>
            <?php if (!$tq_by_kind): ?>
                <p class="tq-caption" style="margin:0">
                    <?php echo t('حين تصلك إشعارات ستصنف هنا بأنواعها — دروس، ومواعيد، وإنجازات، وحساب — وبعدد كل نوع، فتقرأ ما يعنيك وحده.'); ?>
                </p>
            <?php else: ?>
                <div>
                    <div class="tq-kindrow">
                        <span class="tq-row">
                            <span class="tq-icon-box tq-pastel--sky" aria-hidden="true"><?php echo tq_icon('menu', 18); ?></span>
                            <span class="tq-caption"><?php echo t('الكل'); ?></span>
                        </span>
                        <?php echo tq_num(count($tq_all), 'tq-num--sm'); ?>
                    </div>
                    <?php foreach ($tq_by_kind as $label => $k): ?>
                        <div class="tq-kindrow">
                            <span class="tq-row">
                                <span class="tq-icon-box tq-pastel--<?php echo $k['tone']; ?>" aria-hidden="true"><?php echo tq_icon($k['icon'], 18); ?></span>
                                <span class="tq-caption"><?php echo html_escape($label); ?></span>
                            </span>
                            <?php echo tq_num($k['count'], 'tq-num--sm'); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- ملخص -->
        <section class="tq-card tq-card--panel" aria-labelledby="tq-nsum-h">
            <div class="tq-card__head"><h2 class="tq-card__title" id="tq-nsum-h"><?php echo t('ملخص الإشعارات'); ?></h2></div>
            <div class="tq-kindrow">
                <span class="tq-row">
                    <span class="tq-notif__dot" style="background:var(--tq-navy)" aria-hidden="true"></span>
                    <span class="tq-caption"><?php echo t('غير مقروءة'); ?></span>
                </span>
                <?php echo tq_num($tq_unread_count, 'tq-num--sm'); ?>
            </div>
            <div class="tq-kindrow">
                <span class="tq-row">
                    <span class="tq-icon-box tq-pastel--mint" aria-hidden="true"><?php echo tq_icon('calendar', 18); ?></span>
                    <span class="tq-caption"><?php echo t('اليوم'); ?></span>
                </span>
                <?php echo tq_num($tq_today_count, 'tq-num--sm'); ?>
            </div>
            <div class="tq-kindrow">
                <span class="tq-row">
                    <span class="tq-icon-box tq-pastel--lilac" aria-hidden="true"><?php echo tq_icon('clock', 18); ?></span>
                    <span class="tq-caption"><?php echo t('هذا الأسبوع'); ?></span>
                </span>
                <?php echo tq_num($tq_week_count, 'tq-num--sm'); ?>
            </div>
        </section>

        <!-- إعدادات الإشعارات: تفضيلات + الإشعارات البريدية -->
        <section class="tq-card tq-card--panel" aria-labelledby="tq-nset-h">
            <div class="tq-card__head"><h2 class="tq-card__title" id="tq-nset-h"><?php echo t('إعدادات الإشعارات'); ?></h2></div>
            <p class="tq-caption"><?php echo t('تحكم في طريقة استلامك للإشعارات.'); ?></p>

            <?php if (!$tq_prefs): ?>
                <p class="tq-caption" style="margin:0"><?php echo t('لا أنواع إشعارات مفعلة لدور الطالب بعد.'); ?></p>
            <?php else: ?>
                <div style="margin-block:var(--tq-space-m)">
                    <?php foreach (array_slice($tq_prefs, 0, 5) as $p): ?>
                        <div class="tq-prefrow">
                            <span class="tq-caption"><?php echo html_escape($p['label']); ?></span>
                            <span class="tq-chanpair">
                                <?php echo tq_badge($p['system'] ? 'mastered' : 'idle', t('داخل المنصة')); ?>
                                <?php echo tq_badge($p['email'] ? 'mastered' : 'idle', t('بريد')); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <a class="tq-btn tq-btn--secondary tq-btn--block" href="<?php echo base_url('student/settings?s=alerts'); ?>">
                <span aria-hidden="true"><?php echo tq_icon('cog', 18); ?></span>
                <?php echo t('تفضيلات الإشعارات'); ?>
            </a>
            <a class="tq-btn tq-btn--ghost tq-btn--block" href="<?php echo base_url('student/settings?s=alerts'); ?>" style="margin-block-start:var(--tq-space-s)">
                <span aria-hidden="true"><?php echo tq_icon('chat', 18); ?></span>
                <?php echo t('الإشعارات البريدية'); ?>
            </a>
        </section>

        <!-- نصيحة -->
        <section class="tq-card tq-card--panel tq-pastel tq-pastel--mint" aria-labelledby="tq-tip-h">
            <h2 class="tq-pastel__title tq-h2" id="tq-tip-h" style="margin-block-end:var(--tq-space-s)"><?php echo t('نصيحة'); ?></h2>
            <p class="tq-pastel__body" style="margin:0">
                <?php echo t('فعل إشعارات المواعيد لتصلك تنبيهات الحصص والاختبارات قبل وقتها بوقت يكفي للاستعداد، لا في اللحظة نفسها.'); ?>
            </p>
        </section>

    </aside>
</div>

<?php include 'portal_close.php'; ?>
