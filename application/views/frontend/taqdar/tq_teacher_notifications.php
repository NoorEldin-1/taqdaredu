<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * بوابة المعلم — الإشعارات.
 *
 * كان `Taqdar::counts()` يحسب للمعلم عدد إشعاراته غير المقروءة في كل صفحة
 * يفتحها (سطر 91)، ثم لا يعرضه أحد: لا جرس في الترويسة لأن
 * `portal_topbar.php` كان يضبط `notifications => null` للمعلم، ولا بند في
 * قائمته، ولا شاشة في خريطة `Taqdar::teacher()`. عد يجري ويرمى.
 *
 * والشاشة تقرأ الجدول نفسه الذي تقرؤه بوابتا الطالب وولي الأمر
 * (`notifications`)، وتعرض أنواعها بالعناوين نفسها حرفا بحرف: الحدث
 * الواحد يقرأ باسم واحد في البوابات الثلاث، وإلا صار الحديث عن حدثين.
 *
 * وأنواع المعلم تختلف عن أنواع الطالب في المعنى لا في الاسم: «نتيجة
 * امتحان» عند الطالب نتيجته هو، وعند المعلم تسليم ينتظر تصحيحه. فالعنوان
 * الجانبي يقولها بلسان المعلم، والنوع في القاعدة واحد لا يتفرع.
 *
 * والأنماط في `tq_notif_styles.php` — مشتركة مع شاشة الطالب.
 */

$tq_nav   = 'notifications';
$tq_role  = 'teacher';
$tq_title = t('الإشعارات');
$tq_sub   = t('ما جد على طلابك وكورساتك، مجموعا بيومه');
$tq_icon  = 'bell';

$uid = (int) $this->session->userdata('user_id');

/* ---- «تحديد الكل كمقروء» فعل حقيقي، وينفذ قبل أي إخراج ------------- */
if ($this->input->post('action') === 'mark_all_read') {
    $this->db->where('to_user', $uid)->where('status', 0)
             ->update('notifications', ['status' => 1, 'updated_at' => (string) time()]);
    redirect(site_url('teacher/notifications'), 'location', 302);
}

/* ---- الإشعارات ------------------------------------------------------- */
$tq_state = $this->input->get('state', true);
$tq_state = in_array($tq_state, ['unread', 'read'], true) ? $tq_state : 'all';

$tq_all = $this->db->where('to_user', $uid)
    ->order_by('id', 'DESC')->limit(120)
    ->get('notifications')->result_array();

$tq_unread_count = 0;
$tq_read_count   = 0;
foreach ($tq_all as $n) {
    if ((int) $n['status'] === 0) { $tq_unread_count++; } else { $tq_read_count++; }
}

$tq_list = array_values(array_filter($tq_all, static function ($n) use ($tq_state) {
    if ($tq_state === 'unread') return (int) $n['status'] === 0;
    if ($tq_state === 'read')   return (int) $n['status'] === 1;
    return true;
}));

/**
 * التصنيف بلسان المعلم.
 *
 * النوع في `notifications.type` هو نفسه المكتوب لبقية الأدوار — الكتالوج
 * في `Taqdar_events_model` واحد — والمعروض هنا وصفه من موقع المعلم:
 * «تسليم ينتظر تصحيحك» لا «نتيجة امتحانك». ولكل نوع وجهة ينقلك إليها،
 * فالإشعار الذي لا يفتح شيئا خبر لا أداة.
 */
$tq_kinds = [
    'exam_result'      => [t('تسليم ينتظر تصحيحك'),   'clipboard',   'peach', 'teacher/marking'],
    'station_failed'   => [t('رسوب في اختبار محطة'),  'target',      'rose',  'teacher/marking'],
    'inactivity_3days' => [t('طالب انقطع'),            'clock',       'sand',  'teacher/students'],
    'session_request'  => [t('طلب حصة خاصة'),          'video',       'lilac', 'teacher/sessions'],
    'certificate'      => [t('شهادة لطالبك'),          'award',       'sky',   'teacher/students'],
    'weekly_report'    => [t('ملخص أسبوعك'),           'chart',       'mint',  'teacher'],

    // أنواع Academy الأصلية التي قد تصل المعلم
    'course_purchase'                => [t('بيع في كورساتك'),   'wallet', 'mint', 'teacher/wallet'],
    'bundle_purchase'                => [t('بيع في كورساتك'),   'wallet', 'mint', 'teacher/wallet'],
    'offline_payment_suspended_mail' => [t('المدفوعات'),        'wallet', 'peach', 'teacher/wallet'],
    'noticeboard'                    => [t('لوحة المادة'),      'clipboard', 'lilac', 'teacher/courses'],
    'instructor_followups'           => [t('متابعة'),           'chat',   'mint',  'teacher/messages'],
    'signup'                         => [t('الحساب والأمان'),   'users',  'sand',  'teacher/settings'],
    'email_verification'             => [t('الحساب والأمان'),   'lock',   'sand',  'teacher/settings'],
    'forget_password_mail'           => [t('الحساب والأمان'),   'lock',   'sand',  'teacher/settings?s=security'],
    'new_device_login_confirmation'  => [t('الحساب والأمان'),   'lock',   'sand',  'teacher/settings?s=security'],
];
$tq_kind = static function ($type) use ($tq_kinds) {
    return $tq_kinds[$type] ?? [t('تنبيهات أخرى'), 'bell', 'rose', ''];
};

/* ---- «هذا الإشعار وحده» ---------------------------------------------
 *
 * TQ-NOTIF-READ — الصف هنا كان ينتقل بوجهته ولا يقرأ: يفتح المعلم
 * «تسليم ينتظر تصحيحك» فيصحح، ثم يعود فيجد النقطة الزرقاء مكانها —
 * والعداد لا ينقص إلا بـ«تحديد الكل كمقروء»، وهو يمسح ما لم يفتحه.
 * فصار غير المقروء زرا: يقرأ أولا ثم يحول إلى وجهته، فيقع الفعلان بضغطة.
 *
 * والوجهة تشتق من `$tq_kinds` في الخادم لا ترسل في الطلب: عنوان يرسله
 * المتصفح يجعل من هذا النموذج بابا يحول به إلى أي موقع (open redirect).
 *
 * ويوضع بعد `$tq_kind` لأنه يقرأ منها، وقبل أي إخراج فـ`redirect` تعمل. */
if ($this->input->post('action') === 'mark_read') {
    $tq_nid = (int) $this->input->post('id');
    $tq_to  = '';
    if ($tq_nid > 0) {
        $tq_row = $this->db->where('to_user', $uid)->where('id', $tq_nid)
                           ->get('notifications')->row_array();
        if ($tq_row) {
            if ((int) $tq_row['status'] === 0) {
                $this->db->where('to_user', $uid)->where('id', $tq_nid)
                         ->update('notifications', ['status' => 1, 'updated_at' => (string) time()]);
            }
            $tq_to = $tq_kind($tq_row['type'])[3];
        }
    }
    if ($tq_to !== '') {
        redirect(site_url($tq_to), 'location', 302);
    }
    $tq_back = $this->input->post('state', true);
    $tq_back = in_array($tq_back, ['unread', 'read'], true) ? '?state=' . $tq_back : '';
    redirect(site_url('teacher/notifications') . $tq_back, 'location', 302);
}

/* عداد كل نوع — من إشعارات هذا المعلم وحدها */
$tq_by_kind = [];
foreach ($tq_all as $n) {
    [$label, $icon, $tone] = $tq_kind($n['type']);
    if (!isset($tq_by_kind[$label])) {
        $tq_by_kind[$label] = ['count' => 0, 'icon' => $icon, 'tone' => $tone];
    }
    $tq_by_kind[$label]['count']++;
}

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

        <?php /* TQ-SPAM — نسخة كل صف هنا ترسل بالبريد كذلك، فمن يقرأ في
                 هذه الشاشة خبرا لم يصل بريده يقف على الفرق بعينه.
                 وفي العمود الرئيسي لا الجانبي: الجانبي ينتقل تحت القائمة
                 كلها دون 1024 بكسل. */ ?>
        <?php echo tq_spam_notice(array('what' => t('إشعاراتنا'), 'class' => 'tq-spam--top')); ?>

        <div class="tq-row tq-row--between" style="margin-block-end:var(--tq-space-l);flex-wrap:wrap;gap:var(--tq-space-m)">
            <nav class="tq-tabs" aria-label="<?php echo te('تصفية الإشعارات'); ?>" style="margin-block-end:0;border-block-end:0">
                <?php foreach ($tq_states as $key => $info): ?>
                    <a class="tq-tab"
                       href="<?php echo base_url('teacher/notifications') . ($key === 'all' ? '' : '?state=' . $key); ?>"
                       <?php echo tq_active($key, $tq_state); ?>>
                        <?php echo html_escape($info[0]); ?>
                        <span class="tq-tab__n"><?php echo TQ_LRI . (int) $info[1] . TQ_PDI; ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php if ($tq_unread_count > 0): ?>
                <form method="post" action="<?php echo base_url('teacher/notifications'); ?>">
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
                        <?php echo t('هنا يصلك ما يستدعي انتباهك: تسليم ينتظر تصحيحك، وطلب حصة، وطالب انقطع، وبيع في كورساتك — مجموعا بيومه لا مبعثرا.'); ?>
                    </p>
                    <a class="tq-btn tq-btn--primary" href="<?php echo base_url('teacher'); ?>"><?php echo t('عودة إلى اللوحة'); ?></a>
                </div>
            </div>
        <?php else: ?>
            <?php /* نموذج واحد يلف القائمة، وكل صف غير مقروء زر يرسل معرفه.
                     والوجهة لا ترسل معه — تشتق في الخادم من نوع الإشعار. */ ?>
            <form method="post" action="<?php echo base_url('teacher/notifications'); ?>">
                <?php echo tq_csrf(); ?>
                <input type="hidden" name="action" value="mark_read">
                <input type="hidden" name="state" value="<?php echo html_escape($tq_state); ?>">
            <?php foreach ($tq_groups as $label => $items): ?>
                <?php if (!$items) { continue; } ?>
                <section class="tq-daygroup" aria-labelledby="tq-g-<?php echo md5($label); ?>">
                    <span class="tq-daygroup__label" id="tq-g-<?php echo md5($label); ?>"><?php echo html_escape($label); ?></span>
                    <div class="tq-card tq-card--panel" style="padding:var(--tq-space-s)">
                        <?php foreach ($items as $n): ?>
                            <?php
                            [$kind_label, $kind_icon, $kind_tone, $kind_href] = $tq_kind($n['type']);
                            $unread = ((int) $n['status'] === 0);
                            $text   = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $n['description'])));
                            $ts     = (int) $n['created_at'];
                            /* ثلاث حالات لا حالتان:
                               غير المقروء **زر** — يقرأ نفسه ثم يحول إلى وجهته إن كانت له؛
                               والمقروء ذو الوجهة **رابط** — لا شيء يقرأ فيبقى الانتقال وحده؛
                               وما لا وجهة له ولا قراءة **صف نص** — ولا يعرض رابط يفتح
                               الصفحة نفسها ليبدو الصف قابلا للنقر. */
                            $tag    = $unread ? 'button' : ($kind_href !== '' ? 'a' : 'div');
                            ?>
                            <<?php echo $tag; ?> class="tq-notif<?php echo $unread ? ' tq-notif--unread' : ''; ?>"
                                <?php if ($unread): ?>type="submit" name="id" value="<?php echo (int) $n['id']; ?>"
                                <?php elseif ($kind_href !== ''): ?>href="<?php echo base_url($kind_href); ?>"<?php endif; ?>>
                                <span class="tq-notif__dot" aria-hidden="true"></span>
                                <span>
                                    <span class="tq-notif__title"><?php echo tq_iso(html_escape($n['title'])); ?></span>
                                    <span class="tq-notif__line"><?php echo tq_iso(html_escape(mb_substr($text, 0, 90))); ?></span>
                                    <span class="tq-notif__line"><?php echo html_escape($kind_label); ?></span>
                                    <?php if ($unread): ?><span class="tq-sr">غير مقروء — اضغط لتحديده كمقروء<?php echo $kind_href !== '' ? t('والانتقال إليه') : ''; ?></span><?php endif; ?>
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

        <section class="tq-card tq-card--panel" aria-labelledby="tq-kinds-h">
            <div class="tq-card__head"><h2 class="tq-card__title" id="tq-kinds-h"><?php echo t('أنواع ما وصلك'); ?></h2></div>
            <?php if (!$tq_by_kind): ?>
                <p class="tq-caption" style="margin:0">
                    <?php echo t('حين تصلك إشعارات ستصنف هنا بأنواعها — تصحيح، وحصص، وانقطاع، ومبيعات — وبعدد كل نوع.'); ?>
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

        <section class="tq-card tq-card--panel" aria-labelledby="tq-nset-h">
            <div class="tq-card__head"><h2 class="tq-card__title" id="tq-nset-h"><?php echo t('إعدادات الإشعارات'); ?></h2></div>
            <p class="tq-caption">
                <?php echo t('لكل نوع قناتان مستقلتان: داخل المنصة والبريد. وإيقاف قناة لا يوقف الأخرى.'); ?>
            </p>
            <a class="tq-btn tq-btn--secondary tq-btn--block" href="<?php echo base_url('teacher/settings?s=alerts'); ?>">
                <span aria-hidden="true"><?php echo tq_icon('cog', 18); ?></span>
                <?php echo t('تفضيلات التنبيهات'); ?>
            </a>
        </section>

        <section class="tq-card tq-card--panel tq-pastel tq-pastel--mint" aria-labelledby="tq-tip-h">
            <h2 class="tq-pastel__title tq-h2" id="tq-tip-h" style="margin-block-end:var(--tq-space-s)"><?php echo t('قاعدة المقاطعة'); ?></h2>
            <p class="tq-pastel__body" style="margin:0">
                <?php echo t('ما يستدعي فعلا اليوم يقاطعك، وما عداه ينتظر ملخص الأسبوع. طلب حصة بلا رد أربعا وعشرين ساعة يلغى تلقائيا ويعاد للطالب.'); ?>
            </p>
        </section>

    </aside>
</div>

<?php include 'portal_close.php'; ?>
