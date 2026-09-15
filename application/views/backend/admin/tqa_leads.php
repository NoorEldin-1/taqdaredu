<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * عملاء الإعلانات — TQ-META-LEADS.
 *
 * ترتيب الشاشة ترتيب السؤال لا ترتيب الجداول: **أيعمل الربط الآن؟** ثم
 * **من وصل؟** ثم **لماذا لم يصل غيرهم؟** ثم **كيف يضبط؟**.
 *
 * والحال قبل القائمة قصدا: من يفتح شاشة فارغة لا يعرف إن كانت الحملة لم
 * تجلب أحدا أو أن الربط ساكت من أمس — وهما خبران لا خبر، وكل واحد
 * يعالج بغير ما يعالج به الآخر. وسجل النداءات تحت القائمة لا فوقها:
 * الأصل أن يصل، والسجل يقرأ عند الشكوى.
 */
$tq_f      = is_array($filters ?? null) ? $filters : array();
$tq_rows   = is_array($rows ?? null) ? $rows : array();
$tq_tot    = is_array($totals ?? null) ? $totals : array();
$tq_sts    = is_array($statuses ?? null) ? $statuses : array();
$tq_camps  = is_array($campaigns ?? null) ? $campaigns : array();
$tq_hooks  = is_array($hooks ?? null) ? $hooks : array();
$tq_htot   = is_array($hook_tot ?? null) ? $hook_tot : array();
$tq_cfg    = is_array($cfg ?? null) ? $cfg : array();
$tq_ready  = !empty($ready);
$tq_url_cb = (string) ($hook_url ?? '');

$tq_q      = (string) ($tq_f['q'] ?? '');
$tq_st     = (string) ($tq_f['status'] ?? '');
$tq_camp   = (string) ($tq_f['campaign'] ?? '');
$tq_plat   = (string) ($tq_f['platform'] ?? '');

/* المرشحات تجتمع في الرابط: من رشح بالحملة ثم ضغط حالة يفقد حملته لو
   بني الرابط من الحالة وحدها، فيقرأ قائمة أوسع مما طلب ويظنها نتيجته.
   وهي قاعدة الكتالوج نفسها. */
$tq_link = function ($over = array(), $page = 'taqdar_admin/leads') use ($tq_q, $tq_st, $tq_camp, $tq_plat) {
    $q = array_filter(array('status' => $tq_st, 'campaign' => $tq_camp,
                            'platform' => $tq_plat, 'q' => $tq_q), 'strlen');
    foreach ($over as $k => $v) {
        if ((string) $v === '') unset($q[$k]); else $q[$k] = $v;
    }
    return site_url($page . ($q ? '?' . http_build_query($q) : ''));
};

$tq_when = function ($raw) {
    $ts = $raw ? strtotime((string) $raw) : 0;
    if ($ts <= 0) return array('—', '');
    $ago = time() - $ts;
    if ($ago < 3600)  $rel = t('قبل ____ دقيقة', max(1, (int) round($ago / 60)));
    elseif ($ago < 86400) $rel = t('قبل ____ ساعة', (int) round($ago / 3600));
    else              $rel = date('Y/m/d', $ts);
    return array($rel, date('Y/m/d — H:i', $ts));
};
?>

<?php tqa_head(t('عملاء الإعلانات'),
    t('من ملأ نموذج إعلان على فيسبوك أو إنستغرام يصل هنا في ثانيته، ومعه حملته وإعلانه ونموذجه.'),
    'user-check',
    '<a class="tqa-btn tqa-btn--ghost" href="' . html_escape($tq_link(array(), 'taqdar_admin/leads_export')) . '">'
    . tq_icon('download', 16) . ' ' . te('صدر المعروض') . '</a>'
); ?>

<?php /* ══════════ أيعمل الربط الآن؟ ══════════ */ ?>
<div class="tqa-note <?php echo $tq_ready ? '' : 'tqa-note--warn'; ?> tqa-section">
    <span aria-hidden="true"><?php echo tq_icon($tq_ready ? 'check-badge' : 'alert', 18); ?></span>
    <span>
        <?php if ($tq_ready): ?>
            <strong><?php echo t('الربط مضبوط.'); ?></strong>
            <?php echo t('يجلب الخادم بيانات كل عميل جديد من ميتا ويكتبه هنا — بلا نسخ يدوي ولا ملف ينزل. واضغط «اسأل ميتا» أدناه لتتأكد أن الرمز دائم وأن الصفحة مشتركة في الحدث.'); ?>
        <?php else: ?>
            <strong><?php echo t('الربط غير مضبوط بعد.'); ?></strong>
            <?php echo t('لا يجلب الخادم عميلا واحدا حتى يحفظ رمز الصفحة أدناه. والنداءات التي تصل قبل ذلك لا تفقد: تقيد في السجل وتجلب في أول دورة بعد الحفظ.'); ?>
        <?php endif; ?>
    </span>
</div>

<?php /* ══════════ الأعداد ══════════
         والعدد جزء من المرشح لا سطر منفصل: «مؤهل» بلا رقم لا تخبر إن
         كان واحدا أو أربعين. */ ?>
<div class="tqa-toolbar tqa-toolbar--wrap" style="gap:var(--tq-space-xs)">
    <a class="tqa-btn <?php echo $tq_st === '' ? 'tqa-btn--primary' : 'tqa-btn--ghost'; ?>"
       href="<?php echo $tq_link(array('status' => '')); ?>">
        <?php echo t('الكل'); ?> <span class="tqa-num">(<?php echo (int) ($tq_tot['all'] ?? 0); ?>)</span>
    </a>
    <?php foreach ($tq_sts as $k => $s): ?>
        <a class="tqa-btn <?php echo $tq_st === $k ? 'tqa-btn--primary' : 'tqa-btn--ghost'; ?>"
           href="<?php echo $tq_link(array('status' => $k)); ?>">
            <?php echo html_escape($s['label']); ?>
            <span class="tqa-num">(<?php echo (int) ($tq_tot[$k] ?? 0); ?>)</span>
        </a>
    <?php endforeach; ?>
</div>

<form class="tqa-toolbar" method="get" action="<?php echo site_url('taqdar_admin/leads'); ?>"
      style="margin-block-start:var(--tq-space-m)">
    <label class="tqa-sr" for="q"><?php echo t('ابحث في العملاء'); ?></label>
    <input class="tqa-input" type="search" id="q" name="q"
           placeholder="<?php echo te('ابحث باسم أو جوال أو بريد أو معرف ميتا…'); ?>"
           value="<?php echo html_escape($tq_q); ?>">
    <?php /* المرشحات القائمة تركب في نموذج البحث، وإلا مسحها أول بحث. */ ?>
    <?php if ($tq_st !== ''): ?><input type="hidden" name="status" value="<?php echo html_escape($tq_st); ?>"><?php endif; ?>
    <?php if ($tq_camp !== ''): ?><input type="hidden" name="campaign" value="<?php echo html_escape($tq_camp); ?>"><?php endif; ?>
    <?php if ($tq_plat !== ''): ?><input type="hidden" name="platform" value="<?php echo html_escape($tq_plat); ?>"><?php endif; ?>
    <button type="submit" class="tqa-btn tqa-btn--primary"><?php echo tq_icon('search', 16); ?> <?php echo t('ابحث'); ?></button>
    <?php if ($tq_q !== ''): ?>
        <a class="tqa-btn tqa-btn--ghost" href="<?php echo $tq_link(array('q' => '')); ?>"><?php echo t('مسح البحث'); ?></a>
    <?php endif; ?>
</form>

<?php /* ══════════ مرشح الحملة والمصدر ══════════
         وخياراته من **المعروض** لا من واجهة ميتا: حملة أوقفت أمس يبقى
         عملاؤها في الجدول، وقائمة مبنية من ميتا تسقطها فيقرأ المسؤول
         صفوفا لا يستطيع أن يرشح بها. */ ?>
<?php if ($tq_camps): ?>
<div class="tqa-toolbar tqa-toolbar--wrap" style="gap:var(--tq-space-xs);margin-block-start:var(--tq-space-s)">
    <span class="tqa-hint"><?php echo t('الحملة:'); ?></span>
    <a class="tqa-btn tqa-btn--sm <?php echo $tq_camp === '' ? 'tqa-btn--primary' : 'tqa-btn--ghost'; ?>"
       href="<?php echo $tq_link(array('campaign' => '')); ?>"><?php echo t('كل الحملات'); ?></a>
    <?php foreach ($tq_camps as $c): ?>
        <a class="tqa-btn tqa-btn--sm <?php echo $tq_camp === (string) $c['campaign_id'] ? 'tqa-btn--primary' : 'tqa-btn--ghost'; ?>"
           href="<?php echo $tq_link(array('campaign' => (string) $c['campaign_id'])); ?>">
            <?php echo html_escape(trim((string) $c['campaign_name']) !== ''
                ? (string) $c['campaign_name'] : (string) $c['campaign_id']); ?>
            <span class="tqa-num">(<?php echo (int) $c['n']; ?>)</span>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php /* ══════════ القائمة ══════════ */ ?>
<div class="tqa-card tqa-card--flush tqa-section">
<?php if (!$tq_rows): ?>

    <?php tqa_empty(
        $tq_st === '' && $tq_q === '' && $tq_camp === '' ? t('لا عميل بعد') : t('لا عميل بهذا المرشح'),
        $tq_st === '' && $tq_q === '' && $tq_camp === ''
            ? ($tq_ready
                ? t('الصف يكتب هنا في ثانية وصول أول عميل. وإن كانت الحملة تعمل ولا يصل أحد فاضغط «اسأل ميتا» أدناه: الأعطال الثلاثة الشائعة كلها تعطي شاشة فارغة بلا رسالة — رمز مؤقت انتهى، ورمز بلا صلاحية قراءة العملاء، وصفحة لم تشترك في الحدث أصلا.')
                : t('اضبط الربط أدناه أولا: معرف الصفحة ورمزها، ورمز التحقق وسر التطبيق — ثم سجل عنوان الويبهوك في تطبيقك عند ميتا.'))
            : t('جرب مرشحا آخر، أو اعرض الكل.'),
        '', '', 'user-check'
    ); ?>

<?php else: ?>
    <div class="tqa-table__wrap">
    <table class="tqa-table">
        <thead>
            <tr>
                <th><?php echo t('العميل'); ?></th>
                <th><?php echo t('التواصل'); ?></th>
                <th><?php echo t('من أين جاء'); ?></th>
                <th><?php echo t('متى'); ?></th>
                <th><?php echo t('الحال'); ?></th>
                <th><?php echo t('إجراء'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($tq_rows as $r):
            $st   = (string) $r['status'];
            $tone = (string) ($tq_sts[$st]['tone'] ?? 'muted');
            $lbl  = (string) ($tq_sts[$st]['label'] ?? $st);
            list($rel, $full) = $tq_when($r['created_time']);
        ?>
            <tr>
                <td data-label="<?php echo te('العميل'); ?>">
                    <a href="<?php echo site_url('taqdar_admin/lead/' . (int) $r['id']); ?>">
                        <strong><?php echo html_escape(trim((string) $r['full_name']) !== ''
                            ? (string) $r['full_name'] : t('بلا اسم')); ?></strong>
                    </a>
                    <?php /* «أهو مسجل عندنا أصلا؟» يقال في الصف لا في الصفحة:
                             من له حساب قائم لا يباع له من جديد — يسأل عن
                             اشتراكه، ومن اتصل به بعرض تسجيل يبدو أنه لا
                             يعرف عميله. */ ?>
                    <?php if ((int) $r['user_id'] > 0): ?>
                        <br><span class="tqa-badge tqa-badge--info"><?php echo t('له حساب عندنا'); ?></span>
                    <?php endif; ?>
                    <?php if (trim((string) $r['city']) !== ''): ?>
                        <span class="tqa-status__why"><?php echo html_escape((string) $r['city']); ?></span>
                    <?php endif; ?>
                </td>
                <td data-label="<?php echo te('التواصل'); ?>">
                    <?php if (trim((string) $r['phone']) !== ''): ?>
                        <a class="tqa-num" dir="ltr" href="tel:<?php echo html_escape((string) $r['phone']); ?>">
                            <?php echo html_escape((string) $r['phone']); ?></a>
                    <?php endif; ?>
                    <?php if (trim((string) $r['email']) !== ''): ?>
                        <br><span dir="ltr"><?php echo html_escape((string) $r['email']); ?></span>
                    <?php endif; ?>
                    <?php if (trim((string) $r['phone']) === '' && trim((string) $r['email']) === ''): ?>
                        <span class="tqa-hint"><?php echo t('لا وسيلة تواصل في النموذج'); ?></span>
                    <?php endif; ?>
                </td>
                <td data-label="<?php echo te('من أين جاء'); ?>">
                    <span class="tqa-badge tqa-badge--muted">
                        <?php echo html_escape($this->tq_leads->platform_word((string) $r['platform'])); ?>
                    </span>
                    <?php if (trim((string) $r['campaign_name']) !== ''): ?>
                        <span class="tqa-status__why"><?php echo html_escape((string) $r['campaign_name']); ?></span>
                    <?php endif; ?>
                    <?php if (trim((string) $r['ad_name']) !== ''): ?>
                        <span class="tqa-status__why"><?php echo html_escape((string) $r['ad_name']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($r['is_organic'])): ?>
                        <span class="tqa-badge tqa-badge--muted"><?php echo t('من منشور لا إعلان'); ?></span>
                    <?php endif; ?>
                </td>
                <td data-label="<?php echo te('متى'); ?>">
                    <span title="<?php echo html_escape($full); ?>"><?php echo html_escape($rel); ?></span>
                </td>
                <td data-label="<?php echo te('الحال'); ?>">
                    <span class="tqa-badge tqa-badge--<?php echo html_escape($tone); ?>">
                        <?php echo html_escape($lbl); ?>
                    </span>
                </td>
                <td data-label="<?php echo te('إجراء'); ?>">
                    <a class="tqa-btn tqa-btn--sm tqa-btn--ghost"
                       href="<?php echo site_url('taqdar_admin/lead/' . (int) $r['id']); ?>">
                        <?php echo t('افتح'); ?>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
</div>

<?php /* ══════════ سجل النداءات ══════════
         «لماذا لم يصل هذا العميل؟» يجاب من صف لا من ظن، وهو أول ما
         يسأل عند أول شكوى — ولا جواب له في سجل خادم يمسح كل أسبوع. */ ?>
<div class="tqa-card tqa-card--flush tqa-section">
    <div class="tqa-card__head">
        <span class="tqa-iconbox tqa-sky" aria-hidden="true"><?php echo tq_icon('refresh', 20); ?></span>
        <h2><?php echo t('سجل النداءات الواردة من ميتا'); ?></h2>
        <span class="tqa-hint" style="margin-inline-start:auto">
            <?php echo t('وصل'); ?> <span class="tqa-num"><?php echo (int) ($tq_htot['stored'] ?? 0); ?></span> ·
            <?php echo t('مكرر'); ?> <span class="tqa-num"><?php echo (int) ($tq_htot['duplicate'] ?? 0); ?></span> ·
            <?php echo t('فشل'); ?> <span class="tqa-num"><?php echo (int) ($tq_htot['failed'] ?? 0); ?></span> ·
            <?php echo t('مرفوض'); ?> <span class="tqa-num"><?php echo (int) ($tq_htot['rejected'] ?? 0); ?></span>
        </span>
    </div>

    <?php if (!empty($tq_htot['failed'])): ?>
        <div class="tqa-note tqa-note--warn" style="margin:var(--tq-space-l)">
            <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
            <span>
                <strong><span class="tqa-num"><?php echo (int) $tq_htot['failed']; ?></span></strong>
                <?php echo t('نداء وصل ولم يكتمل جلب عميله. ويعاد تلقائيا كل ربع ساعة من'); ?>
                <code class="tqa-code">taqdar_cron reconcile</code><?php echo t('، أو الآن بالزر أدناه — وسبب كل واحد في عمود «الحال».'); ?>
            </span>
        </div>
    <?php endif; ?>

    <?php if (!$tq_hooks): ?>
        <?php tqa_empty(t('لا نداء وارد بعد'),
            t('يكتب صف هنا عند كل نداء تبعثه ميتا — ولو رفضناه أو فشل جلبه. وسجل فارغ مع حملة تعمل يعني أن النداء لا يصل أصلا: الصفحة غير مشتركة في حدث العملاء، أو العنوان المسجل عند ميتا غير عنوان هذه المنصة.'),
            '', '', 'refresh'); ?>
    <?php else: ?>
        <?php
        $tq_htone = array('stored' => 'ok', 'duplicate' => 'muted', 'failed' => 'danger',
                          'ignored' => 'muted', 'rejected' => 'danger', 'pending' => 'info');
        $tq_hword = array('stored' => t('وصل'), 'duplicate' => t('مكرر'), 'failed' => t('فشل'),
                          'ignored' => t('تجاهل'), 'rejected' => t('مرفوض'), 'pending' => t('جار'));
        $tq_sword = array('ok' => t('توقيع صحيح'), 'bad' => t('توقيع لا يطابق'),
                          'absent' => t('بلا فحص توقيع'));
        ?>
        <div class="tqa-table__wrap">
        <table class="tqa-table">
            <thead>
                <tr>
                    <th><?php echo t('الحدث'); ?></th>
                    <th><?php echo t('معرف العميل'); ?></th>
                    <th><?php echo t('التوقيع'); ?></th>
                    <th><?php echo t('الحال'); ?></th>
                    <th><?php echo t('متى'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tq_hooks as $h):
                $hs = (string) $h['status']; ?>
                <tr>
                    <td data-label="<?php echo te('الحدث'); ?>">
                        <span class="tqa-num" dir="ltr"><?php echo html_escape((string) $h['event']); ?></span>
                    </td>
                    <td data-label="<?php echo te('معرف العميل'); ?>">
                        <?php if ((int) $h['lead_row'] > 0): ?>
                            <a class="tqa-num" dir="ltr"
                               href="<?php echo site_url('taqdar_admin/lead/' . (int) $h['lead_row']); ?>">
                                <?php echo html_escape((string) $h['meta_lead_id']); ?></a>
                        <?php else: ?>
                            <span class="tqa-num" dir="ltr"><?php echo html_escape((string) $h['meta_lead_id'] ?: '—'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td data-label="<?php echo te('التوقيع'); ?>">
                        <?php $sg = (string) $h['signature']; ?>
                        <span class="tqa-badge tqa-badge--<?php echo $sg === 'ok' ? 'ok' : ($sg === 'bad' ? 'danger' : 'muted'); ?>">
                            <?php echo html_escape($tq_sword[$sg] ?? $sg); ?>
                        </span>
                    </td>
                    <td data-label="<?php echo te('الحال'); ?>">
                        <span class="tqa-badge tqa-badge--<?php echo $tq_htone[$hs] ?? 'muted'; ?>">
                            <?php echo html_escape($tq_hword[$hs] ?? $hs); ?>
                        </span>
                        <?php if ((int) $h['tries'] > 1): ?>
                            <span class="tqa-num">×<?php echo (int) $h['tries']; ?></span>
                        <?php endif; ?>
                        <?php /* السبب يعرض دائما لا عند الفشل وحده: «تجاهل»
                                 بلا سببه يقرأ عطلا وهو قد يكون حدثا آخر. */ ?>
                        <?php /* والسبب يترجم **عند العرض** لا عند الكتابة: العمود
                                 يكتبه الويبهوك في طلب لا لغة فيه ولا صاحب،
                                 ويكتبه الكرون بلغة الخادم — فلفه هناك يخزن
                                 النص بلغة من كتبه، ومن بدل لغته يقرأ سجله
                                 بلغة لم يعد يقرؤها. وهي قاعدة `tq_wa_log.error`
                                 نفسها. وما جاء من ميتا بالإنجليزية يمر كما هو:
                                 المفتاح عربي فما ليس عربيا لا يطابق شيئا. */ ?>
                        <?php if (!empty($h['note'])): ?>
                            <span class="tqa-status__why" dir="auto"><?php
                                echo html_escape(t((string) $h['note'])); ?></span>
                        <?php endif; ?>
                    </td>
                    <td data-label="<?php echo te('متى'); ?>">
                        <span class="tqa-num" dir="ltr">
                            <?php echo html_escape((string) ($h['done_at'] ?: $h['created_at'])); ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>

    <div class="tqa-actions" style="padding:var(--tq-space-l)">
        <form method="post" action="<?php echo site_url('taqdar_admin/leads_retry'); ?>">
            <?php echo tq_csrf(); ?>
            <button type="submit" class="tqa-btn tqa-btn--ghost">
                <?php echo tq_icon('refresh', 16); ?> <?php echo t('أعد جلب ما تعثر الآن'); ?>
            </button>
        </form>
    </div>
</div>

<?php /* ══════════ الإعداد ══════════
         في ذيل هذه الشاشة لا في «إعدادات المنصة»: هذه هي الشاشة التي
         يظهر فيها أثره — بجوار العملاء الذين وصلوا به. وهو مبدأ تسعيرة
         الحصص نفسه. */ ?>
<div class="tqa-card tqa-section" style="max-inline-size:760px">
    <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
        <span class="tqa-iconbox tqa-mint" aria-hidden="true"><?php echo tq_icon('link', 20); ?></span>
        <h2><?php echo t('ربط ميتا — الإعداد'); ?></h2>
    </div>

    <?php /* العنوان أول ما ينسخ، فأول ما يعرض. وموضع واحد لا يخطأ نسخه. */ ?>
    <div class="tqa-field">
        <label class="tqa-field__label" for="f_cb"><?php echo t('عنوان الويبهوك (Callback URL)'); ?></label>
        <input class="tqa-input tqa-input--ltr" type="text" id="f_cb" dir="ltr" readonly
               onclick="this.select()" value="<?php echo html_escape($tq_url_cb); ?>">
        <span class="tqa-field__hint">
            <?php echo t('يلصق في تطبيقك عند ميتا: Webhooks ← Page ← إضافة اشتراك، ومعه رمز التحقق أدناه. واشترك في حقل'); ?>
            <code class="tqa-code">leadgen</code><?php echo t(' وحده.'); ?>
        </span>
    </div>

    <form method="post" action="<?php echo site_url('taqdar_admin/leads_config'); ?>">
        <?php echo tq_csrf(); ?>

        <div class="tqa-field">
            <label class="tqa-field__label" for="f_page"><?php echo t('معرف الصفحة (Page ID)'); ?></label>
            <input class="tqa-input tqa-input--ltr" type="text" id="f_page" dir="ltr"
                   name="tq_meta_page_id" inputmode="numeric" maxlength="32"
                   autocomplete="off" data-lpignore="true" spellcheck="false" placeholder="102938475601234"
                   value="<?php echo html_escape((string) ($tq_cfg['page_id'] ?? '')); ?>">
            <span class="tqa-field__hint">
                <?php echo t('رقم وحده — من إعدادات الصفحة عند ميتا. وبه يشترك الخادم في حدث العملاء ويتأكد أن الرمز رمز هذه الصفحة بعينها.'); ?>
            </span>
        </div>

        <h3 class="tqa-formsec" style="margin-block:var(--tq-space-xl) var(--tq-space-m)">
            <?php echo t('الأسرار الثلاثة'); ?>
        </h3>
        <p class="tqa-hint" style="margin-block-end:var(--tq-space-m)">
            <?php echo t('كلها تخزن في قاعدة البيانات ولا تظهر بعد حفظها، ولا تكتب في سجل التدقيق. والفارغ لا يمسه — فتصحيح معرف الصفحة لا يمحو رمزها. والمسح يطلب بمربعه صراحة.'); ?>
        </p>

        <?php
        $tq_secrets = array(
            array('tq_meta_page_token', t('رمز الصفحة (Page Access Token)'), !empty($tq_cfg['token']),
                  t('من «مستخدم النظام» في مدير الأعمال، وفيه صلاحية leads_retrieval. ولا يستعمل رمز مؤقت في التشغيل الحقيقي: يعمل ساعات ثم يسكت الربط كله بلا خطأ يظهر — و«اسأل ميتا» أدناه يقول إن كان الرمز دائما أم مؤقتا.'),
                  'EAAG...'),
            array('tq_meta_app_secret', t('سر التطبيق (App Secret)'), !empty($tq_cfg['secret']),
                  t('من إعدادات التطبيق الأساسية عند ميتا. وبه يفحص الخادم توقيع كل نداء وارد فيرد ما لم توقعه ميتا — وبلا حفظه يقبل الباب أي جسم يصله.'),
                  ''),
            array('tq_meta_verify_token', t('رمز التحقق (Verify Token)'), !empty($tq_cfg['verify']),
                  t('نص تختاره أنت، ويكتب هنا وفي شاشة ميتا نفسه حرفا بحرف. وبه توثق ميتا من العنوان مرة واحدة عند تسجيله؛ واختلافه يجعل التسجيل يفشل برسالة واحدة لا تقول أكثر من «Verification failed».'),
                  (string) ($suggest ?? '')),
        );
        foreach ($tq_secrets as $s):
            list($key, $label, $has, $hint, $ph) = $s; ?>
            <div class="tqa-field">
                <label class="tqa-field__label" for="f_<?php echo $key; ?>"><?php echo html_escape($label); ?></label>
                <input class="tqa-input tqa-input--ltr" type="password" id="f_<?php echo $key; ?>"
                       name="<?php echo $key; ?>" dir="ltr" autocomplete="new-password" spellcheck="false"
                       placeholder="<?php echo $has ? te('محفوظ — اتركه فارغا فلا يمس') : html_escape($ph); ?>">
                <span class="tqa-field__hint"><?php echo html_escape($hint); ?></span>
                <?php if ($has): ?>
                    <label class="tqa-check" style="margin-block-start:var(--tq-space-xs)">
                        <input type="checkbox" name="<?php echo $key; ?>_clear" value="1">
                        <span><?php echo t('امسحه'); ?></span>
                    </label>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <h3 class="tqa-formsec" style="margin-block:var(--tq-space-xl) var(--tq-space-m)">
            <?php echo t('الإشعار'); ?>
        </h3>
        <div class="tqa-field">
            <label class="tqa-check">
                <input type="checkbox" name="tq_meta_lead_notify" value="1"
                       <?php echo !empty($tq_cfg['notify']) ? 'checked' : ''; ?>>
                <span><?php echo t('أخبر الإدارة بكل عميل جديد — إشعارا في اللوحة وبريدا.'); ?></span>
            </label>
            <span class="tqa-field__hint">
                <?php echo t('والسرعة هي البيعة: من ملأ نموذجا في إعلان يملأ نموذج غيرنا بعده بدقائق، ومن يتصل أولا يبيع. ولا يخرج بواتساب — ذاك مقصور على المال ورموز التحقق، ومن يصله خبر كل عميل يبلغ عن الرقم فتضيع معه إشعارات المال نفسها.'); ?>
            </span>
        </div>

        <div class="tqa-actions">
            <button type="submit" class="tqa-btn tqa-btn--primary">
                <?php echo tq_icon('check', 16); ?> <?php echo t('احفظ'); ?>
            </button>
        </div>
    </form>
</div>

<?php /* ══════════ التحقق ══════════
         رسالة «حفظ» لا تثبت أن ميتا تعرفنا: ثلاثة أعطال تعطي المشهد
         نفسه حرفا — سجل فارغ ولا خطأ في أي موضع. */ ?>
<div class="tqa-card tqa-section" style="max-inline-size:760px">
    <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
        <span class="tqa-iconbox tqa-sky" aria-hidden="true"><?php echo tq_icon('shield', 20); ?></span>
        <h2><?php echo t('كيف تتأكد أنه يعمل فعلا'); ?></h2>
    </div>

    <ol class="tqa-steps">
        <li><?php echo t('اضغط'); ?> <strong><?php echo t('«اسأل ميتا»'); ?></strong>
            <?php echo t('أدناه: يجيب ثلاثة أسئلة في نداء واحد — أرمز الصفحة صالح ودائم؟ وفيه صلاحية قراءة العملاء؟ وهل الصفحة مشتركة في حدث'); ?>
            <code class="tqa-code">leadgen</code><?php echo t('؟'); ?></li>
        <li><?php echo t('فإن لم تكن مشتركة، اضغط'); ?> <strong><?php echo t('«اشترك في حدث العملاء»'); ?></strong>
            <?php echo t('— وهذه هي الخطوة التي تنسى أكثر من غيرها: التطبيق مضبوط والرمز صحيح ولا يصل نداء واحد أبدا.'); ?></li>
        <li><?php echo t('ثم أرسل عميلا تجريبيا من أداة ميتا'); ?>
            <a href="https://developers.facebook.com/tools/lead-ads-testing/" target="_blank" rel="noopener">Lead Ads Testing Tool</a>
            <?php echo t('— ويجب أن يظهر صفه في القائمة أعلاه في ثوان، وسطره في سجل النداءات.'); ?></li>
        <li><?php echo t('وإن ظهر السطر ولم يظهر العميل فالسبب مكتوب في عمود «الحال» بنصه — ولا يحتاج تخمينا.'); ?></li>
    </ol>

    <div class="tqa-actions" style="margin-block-start:var(--tq-space-l);gap:var(--tq-space-s);flex-wrap:wrap">
        <form method="post" action="<?php echo site_url('taqdar_admin/leads_probe'); ?>">
            <?php echo tq_csrf(); ?>
            <button type="submit" class="tqa-btn tqa-btn--ghost">
                <?php echo tq_icon('shield', 16); ?> <?php echo t('اسأل ميتا'); ?>
            </button>
        </form>
        <form method="post" action="<?php echo site_url('taqdar_admin/leads_subscribe'); ?>">
            <?php echo tq_csrf(); ?>
            <button type="submit" class="tqa-btn tqa-btn--ghost">
                <?php echo tq_icon('link', 16); ?> <?php echo t('اشترك في حدث العملاء'); ?>
            </button>
        </form>
    </div>

    <p class="tqa-hint" style="margin-block-start:var(--tq-space-l)">
        <?php echo t('وبيانات العملاء تبقى عند ميتا تسعين يوما وحدها، فما لم يجلب في تلك المدة لا يجلب بعدها أبدا. ولذلك يعاد ما يتعثر تلقائيا ولا ينتظر أحدا.'); ?>
    </p>
</div>
