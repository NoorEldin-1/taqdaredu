<?php
defined('BASEPATH') or exit('No direct script access allowed');
$M = &get_instance()->taqdar_admin_model;

$labels = array('pending' => t('معلق'), 'active' => t('نشط'), 'cancelled' => t('ملغى'), 'expired' => t('منته'));
/* النبرة بأسماء `tqa-badge` لا بأسماء Bootstrap: كانت `warning`/`success`
   وهما صنفا شارة القالب القديم، فتخرج شارة الحالة بلون الحزمة لا بلون
   الهوية. و«منته» ليست خطرا — انتهاء الأجل في وقته حال طبيعية، والأحمر
   عليها يجعل نصف الجدول يقرأ إنذارا. */
$tq_tone = array('pending' => 'warn', 'active' => 'ok', 'cancelled' => 'danger', 'expired' => 'muted');
?>

<div class="tqa-head">
    <div>
        <h1><?php echo t('الاشتراكات'); ?></h1>
        <p><?php echo t('حالة كل مشترك، وتفعيل التحويلات البنكية بعد التحقق منها.'); ?></p>
    </div>
</div>

<?php
/* اشتراك نشط بلا بنود = طالب دفع وبوابته فارغة، بلا خطأ في أي سجل.
   يقع حين يفعل الاشتراك بـUPDATE مباشر بدل المرور بـ`activate()`.
   والعدد يقاس هنا لا يفترض: تنبيه دائم يتجاهل بعد أسبوع. */
$tq_broken = (int) get_instance()->db->query(
    'SELECT COUNT(*) AS n FROM `subscriptions` s
      WHERE s.`status` IN ("active","cancelled")
        AND NOT EXISTS (SELECT 1 FROM `subscription_items` i
                         WHERE i.`subscription_id` = s.`id`)'
)->row()->n;
?>
<?php
/* اشتراك نشط ينقصه تسجيل — TQ-ENROL-STALE.
   البنود تجيب `is_entitled()`، وصفوف `enrol` تجيب عشر شاشات لا تسأل
   غيرها: كورساتي ودروسي وطلاب المعلم والمواد والتقارير. وهي تكتب مرة
   واحدة عند التفعيل، فكل ما ينشر بعد البيع لا يبلغ مشتركا قائما —
   يشاهد دروسه ويقرأ «لا كورسات بعد» في الشاشة المجاورة.
   والعد هنا بالفرق نفسه الذي يجسده الإصلاح، لا بتقدير. */
$tq_stale = (int) get_instance()->db->query(
    'SELECT COUNT(DISTINCT s.`id`) AS n
       FROM `subscriptions` s
       JOIN `subscription_items` si ON si.`subscription_id` = s.`id`
       JOIN `paths` p ON p.`status` = "published" AND p.`course_id` > 0
            AND ( (si.`entity_type` = "grade"   AND p.`grade_id`   = si.`entity_id`)
               OR (si.`entity_type` = "subject" AND p.`subject_id` = si.`entity_id`)
               OR (si.`entity_type` = "path"    AND p.`id`         = si.`entity_id`)
               OR  si.`entity_type` = "all" )
       JOIN `course` c ON c.`id` = p.`course_id`
       LEFT JOIN `enrol` e ON e.`course_id` = p.`course_id` AND e.`user_id` = s.`user_id`
      WHERE s.`status` IN ("active","cancelled")
        AND e.`id` IS NULL'
)->row()->n;
?>
<?php if ($tq_broken > 0 || $tq_stale > 0): ?>
    <div class="tqa-note tqa-note--warn">
        <?php if ($tq_broken > 0): ?>
            <p style="margin:0 0 6px">
                <strong><?php echo $tq_broken; ?> <?php echo t('اشتراكا نشطا بلا بنود.'); ?></strong>
                <?php echo t('هذه الاشتراكات مدفوعة وحالتها نشطة، لكن محتواها'); ?> <strong><?php echo t('لا يفتح للطالب'); ?></strong>
                <?php echo t('لأن نطاقها لم ينسخ بنودا. يقع هذا حين يفعل الاشتراك من خارج زر التفعيل.'); ?>
            </p>
        <?php endif; ?>
        <?php if ($tq_stale > 0): ?>
            <p style="margin:0">
                <strong><?php echo $tq_stale; ?> <?php echo t('اشتراكا نشطا ينقصه تسجيل.'); ?></strong>
                <?php echo t('محتوى نشر'); ?> <strong><?php echo t('بعد'); ?></strong> <?php echo t('شرائهم، فهو يفتح لهم فعلا ولا يظهر في «كورساتي» ولا «دروسي» ولا في قوائم طلاب معلمه — لأن تلك الشاشات تقرأ جدول التسجيل، وهو يكتب مرة واحدة يوم التفعيل.'); ?>
            </p>
        <?php endif; ?>
        <form method="post" action="<?php echo site_url('taqdar_admin/subscriptions_repair'); ?>"
              style="margin-block-start:10px">
            <?php echo tq_csrf(); ?>
            <button type="submit" class="tqa-btn tqa-btn--primary"><?php echo t('أعد بناء البنود والتسجيلات'); ?></button>
        </form>
    </div>
<?php endif; ?>


<?php if (!$gateway_active): ?>
    <div class="tqa-note tqa-note--warn">
        <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
        <span>
            <strong><?php echo t('لا دفع أونلاين اليوم.'); ?></strong>
            <?php echo t('بوابة تاب غير مفعلة أو بلا مفتاح سري، فالمسار العامل هو التحويل البنكي وحده: يشترك الطالب فينشأ اشتراك «معلق» وفاتورة، ثم تفعله من هنا بعد أن تتحقق من الحوالة.'); ?>
            <a href="<?php echo site_url('taqdar_admin/tap'); ?>"><?php echo t('اضبط بوابة تاب'); ?></a>
            <?php echo t('ليصير الاشتراك يفعل نفسه عند الدفع.'); ?>
        </span>
    </div>
<?php else: ?>
    <div class="tqa-note">
        <span aria-hidden="true"><?php echo tq_icon('card', 18); ?></span>
        <span>
            <?php echo t('الدفع بالبطاقة مفعل: من يدفع يفعل اشتراكه بنفسه ولا ينتظر تفعيلا يدويا. وما يظهر «معلقا» هنا هو من اختار التحويل البنكي أو من لم يكمل دفعته — وحال كل دفعة في'); ?> <a href="<?php echo site_url('taqdar_admin/tap'); ?>"><?php echo t('شاشة بوابة تاب'); ?></a>.
        </span>
    </div>
<?php endif; ?>

<?php
/* بطاقات الرأس — كانت أربعة أرقام في `tqa-stack`، وهي شبكة **بعمود
   واحد**: فتخرج أربع بطاقات بعرض الشاشة كاملا واحدة تحت أخرى بطول
   شاشتين، والرقم فيها ٣٤ بكسلا وحده في فراغ عرضه ألف — وهي أول ما يقع
   عليه بصر من يفتح الشاشة.

   وأربعة أرقام بيضاء متطابقة لا تقول أيها خبر وأيها عمل ينتظر:
   «فواتير غير مدفوعة ١١» و«اشتراكات نشطة ٧» متضادان في المعنى وسيان
   في الشكل. فصارت لكل واحدة نبرتها وأيقونتها، وسطر تحت الرقم يقول ما
   يفعل به — و«بانتظار التفعيل» و«غير مدفوعة» هما وحدهما ما يفتح هذه
   الشاشة أصلا.

   و«إجمالي المحصل» بينها لا في ترويسة الجدول: هو رقم من جنسها. */
?>
<div class="tqa-stack tqa-stack--stats">
    <?php echo tqa_stat(t('إجمالي المحصل'), tqa_money($stats['revenue']),
        array('icon' => 'wallet', 'tone' => 'ok',
              'hint' => t('كل ما سدد فعلا'))); ?>

    <?php echo tqa_stat(t('اشتراكات نشطة'), (int) $stats['active'],
        array('icon' => 'check-badge', 'tone' => 'ok',
              'hint' => t('محتواها مفتوح الآن'))); ?>

    <?php echo tqa_stat(t('بانتظار التفعيل'), (int) $stats['pending'],
        array('icon' => 'clock', 'tone' => (int) $stats['pending'] > 0 ? 'warn' : 'info',
              'hint' => (int) $stats['pending'] > 0
                    ? t('تحقق من الحوالة ثم فعل من القائمة')
                    : t('لا شيء ينتظرك'))); ?>

    <?php echo tqa_stat(t('فواتير غير مدفوعة'), (int) $stats['unpaid'],
        array('icon' => 'receipt', 'tone' => (int) $stats['unpaid'] > 0 ? 'danger' : 'info',
              'hint' => t('صدرت ولم يصل مقابلها'),
              'href' => site_url('taqdar_admin/module/invoices'))); ?>

    <?php echo tqa_stat(t('الباقات'), (int) $stats['plans'],
        array('icon' => 'package', 'tone' => 'info',
              'hint' => t('المعروض للبيع'),
              'href' => site_url('taqdar_admin/module/plans'))); ?>
</div>

<div class="tqa-card tqa-card--flush">
    <div class="tqa-card__head">
        <span class="tqa-iconbox tqa-sky" aria-hidden="true"><?php echo tq_icon('receipt', 20); ?></span>
        <div style="min-inline-size:0">
            <h2><?php echo t('سجل الاشتراكات'); ?></h2>
            <span class="tqa-media__sub">
                <?php echo t('باقة أو مسارا أو كورسا مفردا — أحدثها أولا.'); ?>
            </span>
        </div>
        <span class="tqa-badge tqa-badge--muted">
            <span class="tqa-num"><?php echo count($rows); ?></span>&nbsp;<?php echo t('صفا'); ?>
        </span>
    </div>
    <div>
        <?php if (empty($rows)): ?>

            <div class="tqa-empty">
                <h3><?php echo t('لا اشتراكات بعد'); ?></h3>
                <p><?php echo t('أضف الباقات أولا من'); ?> <a href="<?php echo site_url('taqdar_admin/module/plans'); ?>"><?php echo t('شاشة الباقات'); ?></a><?php echo t('، ثم ستظهر هنا اشتراكات الطلاب.'); ?></p>
            </div>

        <?php else: ?>

            <?php /* TQ-ROW-SPLIT — عشرة أعمدة صارت ستة، وما ذهب لم يحذف
                     بل ضم إلى ما يفسره.

                     كان الصف يقرأ شريطا واحدا: «يبدأ» و«ينتهي» عمودان
                     متجاوران بتاريخين لا يقول أحدهما شيئا بلا الآخر،
                     و«الوسيلة» عمود بكلمة واحدة تخص المبلغ الذي يجاوره،
                     و«الدورة» عمود بشارة ورقم أيام. فصارت كل خلية طبقتين:
                     القيمة وتحتها ما يفسرها — وهو ترتيب يقرأ بلمحة لا
                     بمسح أفقي عبر عشرة أعمدة تخرج ثلاثة منها من الإطار.

                     والجدول `tqa-table` لا `table`: به يلتصق الرأس عند
                     التمرير، وتصير الصفوف بطاقات على الجوال، ويظهر شريط
                     الصف تحت المؤشر. */ ?>
            <div class="tqa-table__wrap">
                <table class="tqa-table tqa-table--zebra">
                    <thead>
                        <tr>
                            <th class="tqa-col--tight">#</th>
                            <th><?php echo t('المشترك'); ?></th>
                            <th><?php echo t('ما اشترى'); ?></th>
                            <?php /* TQ-CYCLE-BUY — الدورة عمود لا حاشية: بعد ان صار
                                     الشهري يباع صار في القائمة صفان لباقة واحدة
                                     بمبلغين، و«399» و«42» تحت اسم واحد بلا عمود يفرق
                                     بينهما تقرآن خطأ في الحساب لا اختلاف مدة. */ ?>
                            <th><?php echo t('المدفوع'); ?></th>
                            <th><?php echo t('الحالة والمدة'); ?></th>
                            <th class="tqa-col--acts"><span class="tqa-sr"><?php echo t('إجراءات'); ?></span></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r):
                        $st = $r['status'];
                        // منته فعليا وإن لم يمر الكرون بعد
                        if ($st === 'active' && !empty($r['ends_at']) && strtotime($r['ends_at']) < time()) {
                            $st = 'expired';
                        }
                        $tq_uname = trim((string) ($r['user_name'] ?? '')) !== ''
                                  ? (string) $r['user_name'] : ('#' . (int) $r['user_id']);
                    ?>
                        <tr>
                            <td class="tqa-col--tight" data-label="#">
                                <span class="tqa-num"><?php echo (int) $r['id']; ?></span>
                            </td>

                            <?php /* المشترك: وجه واسم وبريد. والاسمان يتشابهان في
                                     ثلاثمئة صف، والبريد لا يتشابه — ومن يفعل حوالة
                                     يتحقق ممن يفعل لها قبل أن يضغط. */ ?>
                            <td data-label="<?php echo te('المشترك'); ?>">
                                <div class="tqa-media">
                                    <?php echo tqa_avatar($r['user_image'] ?? '', $tq_uname); ?>
                                    <div class="tqa-cell">
                                        <span class="tqa-cell__main"><?php echo html_escape($tq_uname); ?></span>
                                        <?php if (trim((string) ($r['user_email'] ?? '')) !== ''): ?>
                                            <span class="tqa-cell__sub tqa-mono tqa-mono--dim"><?php echo html_escape($r['user_email']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <?php
                            /* TQ-COURSE-SALE — **ثلاث وحدات بيع في جدول واحد.**
                               وكان العمود يقرأ `plan_name` وحده، فيطبع «—»
                               على كل شراء مسار أو كورس مفرد: يقرأ المسؤول
                               صفا بمبلغ ومشتر وحالة ولا يعرف ما بيع فيه،
                               فيفعل حوالة على العمياء. والنوع يقال مع
                               الاسم — «كورس مفرد» غير «باقة». */
                            $tq_what = ''; $tq_kind = t('باقة'); $tq_kt = 'info';
                            if (trim((string) ($r['plan_name'] ?? '')) !== '') {
                                $tq_what = (string) $r['plan_name'];
                            } elseif (trim((string) ($r['course_name'] ?? '')) !== '') {
                                $tq_what = (string) $r['course_name']; $tq_kind = t('كورس مفرد'); $tq_kt = 'warn';
                            } elseif (trim((string) ($r['path_name'] ?? '')) !== '') {
                                $tq_what = (string) $r['path_name']; $tq_kind = t('مسار'); $tq_kt = 'muted';
                            } else {
                                $tq_kind = '';
                            }
                            ?>
                            <td data-label="<?php echo te('ما اشترى'); ?>">
                                <div class="tqa-cell">
                                    <span class="tqa-cell__main"><?php echo html_escape($tq_what !== '' ? $tq_what : '—'); ?></span>
                                    <?php if ($tq_kind !== ''): ?>
                                        <span class="tqa-cell__sub">
                                            <span class="tqa-badge tqa-badge--<?php echo $tq_kt; ?>"><?php echo html_escape($tq_kind); ?></span>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <?php
                            /* الصف القديم بلا `cycle` (كتب قبل العمود) يقرأ من
                               مدته لا يعرض فراغا: كل ما في القاعدة قبل اليوم
                               دورة الباقة نفسها. */
                            $tq_cl = array('annual' => t('سنوي'), 'quarterly' => t('ربع سنوي'),
                                           'monthly' => t('شهري'), 'free' => t('مجانية'));
                            $tq_ck = isset($r['cycle']) ? (string) $r['cycle'] : '';
                            $tq_dy = (int) (isset($r['days']) ? $r['days'] : 0);
                            if ($tq_ck === '' && $tq_dy > 0) {
                                $tq_ck = ($tq_dy >= 300) ? 'annual' : (($tq_dy >= 80) ? 'quarterly' : 'monthly');
                            }
                            ?>
                            <?php /* المبلغ ووسيلته ودورته في خلية واحدة: الثلاثة
                                     يجيبون سؤالا واحدا — «بكم بيع وكيف؟» — وكانوا
                                     ثلاثة أعمدة متباعدة تقرأ بالمسح لا باللمحة. */ ?>
                            <td data-label="<?php echo te('المدفوع'); ?>">
                                <div class="tqa-cell">
                                    <span class="tqa-cell__main tqa-num"><?php echo tqa_money($r['price']); ?></span>
                                    <span class="tqa-cell__sub tqa-cell__row">
                                        <?php if ($tq_ck !== '' && isset($tq_cl[$tq_ck])): ?>
                                            <span><?php echo $tq_cl[$tq_ck]; ?></span>
                                            <?php if ($tq_dy > 0): ?>
                                                <span>· <?php echo tqa_ltr($tq_dy); ?> <?php echo t('يوما'); ?></span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if ($r['method']): ?>
                                            <span>· <?php echo tqa_ltr($r['method']); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </td>

                            <?php /* الحالة ومداها: «نشط» وحدها لا تقول إلى متى،
                                     و«ينتهي ٢٠٢٦-٠١-١٤» وحدها لا تقول أنشط هو أم
                                     ملغى. وهما عمودان متباعدان كانا. */ ?>
                            <td data-label="<?php echo te('الحالة والمدة'); ?>">
                                <div class="tqa-cell">
                                    <span class="tqa-cell__main">
                                        <span class="tqa-badge tqa-badge--dot tqa-badge--<?php echo $tq_tone[$st]; ?>">
                                            <?php echo $labels[$st]; ?>
                                        </span>
                                    </span>
                                    <span class="tqa-cell__sub tq-ltr" dir="ltr">
                                        <?php
                                        $tq_a = $r['started_at'] ? date('Y-m-d', strtotime($r['started_at'])) : '—';
                                        $tq_b = $r['ends_at']    ? date('Y-m-d', strtotime($r['ends_at']))    : '—';
                                        echo html_escape($tq_a . ' → ' . $tq_b);
                                        ?>
                                    </span>
                                </div>
                            </td>

                            <?php
                            /* TQ-ROW-CLUTTER — كل ما يفعل بالصف في قائمة واحدة.

                               كانت الخلية تحمل **نموذجين وحقلي نص وكاشفا**:
                               حقل «مرجع الحوالة» مع زر «فعل»، ثم `<details>`
                               فيه قائمة القسمة ونموذج ثان بحقل «السبب». فصار
                               الصف ضعف ارتفاع جاره (حقل النص وحده ٤٤ بكسلا)،
                               وأخذ العمود مئة وثمانين بكسلا من جدول عشرة
                               أعمدة، والكاشف يفتح **داخل** الصف فيدفع ما تحته.

                               والقائمة تحل الثلاثة: عرض العمود عرض زر، وحقول
                               الإجراء ألواح داخلها، والقسمة تقرأ حين تطلب. */
                            $tq_acts = array();
                            $tq_sh   = isset($shares[(int) $r['id']]) ? $shares[(int) $r['id']] : array();

                            if ($r['status'] === 'pending'):
                                $tq_acts[] = array(
                                    'panel'   => t('تفعيل بتحويل بنكي'),
                                    'icon'    => 'bank',
                                    'action'  => 'taqdar_admin/subscription_activate/' . (int) $r['id'],
                                    'submit'  => t('فعل الاشتراك'),
                                    'sub'     => t('يسدد الاشتراك ويفتح محتواه فورا.'),
                                    'fields'  => array(array(
                                        'name'        => 'reference',
                                        'placeholder' => t('مرجع الحوالة'),
                                        'required'    => true,
                                        'ltr'         => true,
                                    )),
                                    'confirm' => array(
                                        'title' => t('تفعيل الاشتراك'),
                                        'body'  => t('سيسدد الاشتراك وتفتح باقته للطالب فورا. تأكد من وصول الحوالة أولا.'),
                                        'ok'    => t('فعل الاشتراك'),
                                    ),
                                );
                            elseif ($r['status'] === 'active'):
                                /* الإلغاء لا يصادر المدفوع — المدة تكمل. يقال ذلك
                                   في نص التأكيد نفسه لا بعده. */
                                $tq_acts[] = array(
                                    'label'   => t('إلغاء التجديد'),
                                    'sub'     => t('يبقى صالحا حتى تاريخ انتهائه'),
                                    'icon'    => 'close',
                                    'tone'    => 'danger',
                                    'action'  => 'taqdar_admin/subscription_cancel/' . (int) $r['id'],
                                    'confirm' => array(
                                        'title' => t('إلغاء التجديد'),
                                        'body'  => t('يبقى الاشتراك صالحا حتى تاريخ انتهائه، ولا يجدد بعده.'),
                                        'ok'    => t('ألغ التجديد'),
                                        'tone'  => 'danger',
                                    ),
                                );
                            endif;

                            /* ── قسمة هذه البيعة — TQ-REVENUE-RESPLIT ──────
                               «باعوا صفي ولم يصلني شيء» أول ما يسأل عنه معلم،
                               ولم يكن في اللوحة موضع واحد يجيب. والقسمة تجمد
                               وقت التفعيل بالقاعدة — ونشر عشرين درسا غدا لا
                               يعيد حساب بيعة أمس — لكن حين يحذف المحتوى
                               المقسوم عليه ثم ينشر غيره في الصف نفسه يبقى
                               القيد لمن لا محتوى له. وهذا اللوح هو المخرج،
                               بقرار مسؤول لا تلقائيا. */
                            if ($tq_sh || ((int) $r['price'] > 0 && in_array($r['status'], array('active', 'cancelled'), true))):
                                if ($tq_acts) $tq_acts[] = array('sep' => true);

                                if ($tq_sh):
                                    $tq_list = array();
                                    foreach ($tq_sh as $tq_s) {
                                        $tq_list[] = array(
                                            'name'  => $tq_s['teacher_name'] ?: ('#' . $tq_s['teacher_id']),
                                            'value' => tqa_money($tq_s['amount_halalas']) . ' <span class="tqa-dim">'
                                                     . t('(____ من ____ درسا)', array((int) $tq_s['lessons'], (int) $tq_s['lessons_total']))
                                                     . '</span>',
                                        );
                                    }
                                    $tq_acts[] = array('title' => t('قسمة إيراد هذه البيعة'), 'list' => $tq_list);
                                else:
                                    $tq_acts[] = array('title' => t('قسمة إيراد هذه البيعة'), 'list' => array());
                                    $tq_acts[] = array('note' => t('لم يقيد لأحد. لا مسار منشور بمعلم في نطاق الباقة وقت البيع.'));
                                endif;

                                $tq_acts[] = array(
                                    'panel'   => t('أعد القسمة'),
                                    'icon'    => 'refresh',
                                    'tone'    => 'danger',
                                    'action'  => 'taqdar_admin/subscription_resplit/' . (int) $r['id'],
                                    'submit'  => t('أعد القسمة'),
                                    'sub'     => t('ينقل مالا بين المحافظ، ويسجل في سجل التدقيق.'),
                                    'fields'  => array(array(
                                        'name'        => 'reason',
                                        'placeholder' => t('السبب'),
                                        'required'    => true,
                                        'maxlength'   => 200,
                                    )),
                                    'confirm' => array(
                                        'title' => t('إعادة قسمة الإيراد'),
                                        'body'  => t('تعكس القيود القائمة على هذه البيعة وتقسمها من جديد على المستحقين الآن. ينقل مال بين المحافظ، ويسجل في سجل التدقيق.'),
                                        'ok'    => t('أعد القسمة'),
                                        'tone'  => 'danger',
                                    ),
                                );
                            endif;
                            ?>
                            <td class="tqa-col--acts" data-label="<?php echo te('إجراءات'); ?>">
                                <?php if ($tq_acts): ?>
                                    <?php /* `wide` لأن هذه القائمة تحمل تفصيلا لا
                                             أمرا: لوح تفعيل بحقله، وقسمة الإيراد
                                             صفا لكل معلم، ولوح إعادة قسمة بسببه.
                                             وعرض القائمة القاطع (٣٤٠) يكسر اسم
                                             المعلم ومبلغه سطرين في كل صف. */ ?>
                                    <?php echo tqa_rowmenu($tq_acts, array(
                                        'wide'  => true,
                                        'title' => $tq_uname,
                                        'sub'   => ($tq_what !== '' ? $tq_what . ' · ' : '') . '#' . (int) $r['id'],
                                    )); ?>
                                <?php else: ?>
                                    <span class="tqa-dim">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </div>
</div>

