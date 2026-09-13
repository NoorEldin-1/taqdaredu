<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * العميل المحتمل الواحد — TQ-META-LEADS.
 *
 * القائمة تجيب سؤال المسح («من وصل؟ وما حاله؟»)، وهذه الشاشة تجيب سؤال
 * الحادثة: «هذا بعينه — بم أجاب؟ ومن أي إعلان جاء؟ وماذا فعلنا معه؟».
 * وهو مبدأ TQ-SUB-DETAIL نفسه: إقحام ذلك في صف الجدول يجعل القائمة التي
 * تجيب سؤال المسح لا تمسح بعده.
 *
 * وترتيبها ترتيب المكالمة التي ستجرى: من هو وكيف أتصل به، ثم بم أجاب،
 * ثم من أين جاء، ثم قرار المتابعة — وهو آخرها لأنه يكتب بعد المكالمة
 * لا قبلها.
 */
$r        = is_array($row ?? null) ? $row : array();
$tq_ans   = is_array($answers ?? null) ? $answers : array();
$tq_sts   = is_array($statuses ?? null) ? $statuses : array();

$tq_st    = (string) ($r['status'] ?? 'new');
$tq_tone  = (string) ($tq_sts[$tq_st]['tone'] ?? 'muted');
$tq_lbl   = (string) ($tq_sts[$tq_st]['label'] ?? $tq_st);
$tq_name  = trim((string) ($r['full_name'] ?? ''));
$tq_phone = trim((string) ($r['phone'] ?? ''));
$tq_mail  = trim((string) ($r['email'] ?? ''));

/* والأسئلة القياسية لا تعاد في «بم أجاب»: الاسم والجوال والبريد
   والمدينة معروضة أعلاه بأعمدتها، وتكرارها يجعل من يقرأ يظن أنها أجوبة
   أخرى — والسؤال الذي كتبه صاحب الحملة هو ما يقرأ هنا. */
$tq_std   = array('full_name', 'name', 'first_name', 'last_name', 'email',
                  'phone_number', 'phone', 'city');
$tq_extra = array();
foreach ($tq_ans as $a) {
    if (in_array(strtolower((string) ($a['name'] ?? '')), $tq_std, true)) continue;
    $tq_extra[] = $a;
}
?>

<?php tqa_head($tq_name !== '' ? $tq_name : t('عميل محتمل بلا اسم'),
    t('عميل وصل من إعلانات ميتا. وكل ما في هذه الشاشة جاء من ميتا كما ورد، إلا حال المتابعة وملاحظتها.'),
    'user-check',
    '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('taqdar_admin/leads') . '">'
    . tq_icon('chev-next', 16) . ' ' . te('كل العملاء') . '</a>'
); ?>

<?php /* ══════════ من هو وكيف أتصل به ══════════ */ ?>
<div class="tqa-card">
    <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
        <span class="tqa-iconbox tqa-mint" aria-hidden="true"><?php echo tq_icon('user', 20); ?></span>
        <h2><?php echo t('بيانات التواصل'); ?></h2>
        <span style="margin-inline-start:auto">
            <span class="tqa-badge tqa-badge--<?php echo html_escape($tq_tone); ?>">
                <?php echo html_escape($tq_lbl); ?>
            </span>
        </span>
    </div>

    <dl class="tqa-fieldgrid" style="margin:0">
        <div>
            <dt class="tqa-field__label"><?php echo t('الاسم'); ?></dt>
            <dd style="margin:0"><?php echo $tq_name !== ''
                ? html_escape($tq_name)
                : '<span class="tqa-dim">' . te('لم يكتب في النموذج') . '</span>'; ?></dd>
        </div>
        <div>
            <dt class="tqa-field__label"><?php echo t('الجوال'); ?></dt>
            <dd class="tqa-num" style="margin:0" dir="ltr">
                <?php if ($tq_phone !== ''): ?>
                    <a href="tel:<?php echo html_escape($tq_phone); ?>"><?php echo html_escape($tq_phone); ?></a>
                <?php else: ?>
                    <span class="tqa-dim">—</span>
                <?php endif; ?>
            </dd>
        </div>
        <div>
            <dt class="tqa-field__label"><?php echo t('البريد'); ?></dt>
            <dd class="tqa-num" style="margin:0" dir="ltr">
                <?php if ($tq_mail !== ''): ?>
                    <a href="mailto:<?php echo html_escape($tq_mail); ?>"><?php echo html_escape($tq_mail); ?></a>
                <?php else: ?>
                    <span class="tqa-dim">—</span>
                <?php endif; ?>
            </dd>
        </div>
        <div>
            <dt class="tqa-field__label"><?php echo t('المدينة'); ?></dt>
            <dd style="margin:0"><?php echo html_escape(trim((string) ($r['city'] ?? '')) ?: '—'); ?></dd>
        </div>
        <div>
            <dt class="tqa-field__label"><?php echo t('وقت التسجيل'); ?></dt>
            <dd class="tqa-num" style="margin:0" dir="ltr"><?php
                echo !empty($r['created_time'])
                   ? html_escape(date('Y-m-d H:i', strtotime((string) $r['created_time']))) : '—'; ?></dd>
        </div>
        <div>
            <dt class="tqa-field__label"><?php echo t('وقت وصوله إلينا'); ?></dt>
            <dd class="tqa-num" style="margin:0" dir="ltr"><?php
                echo !empty($r['received_at'])
                   ? html_escape(date('Y-m-d H:i', strtotime((string) $r['received_at']))) : '—'; ?></dd>
        </div>
    </dl>

    <?php /* «أهو مسجل عندنا أصلا؟» أول ما يسأل قبل المكالمة: من له حساب
             قائم لا يعرض عليه تسجيل — يسأل عن اشتراكه. والمطابقة بالبريد
             أو بآخر تسع خانات من الجوال. */ ?>
    <?php if ((int) ($r['user_id'] ?? 0) > 0): ?>
        <div class="tqa-note" style="margin-block-start:var(--tq-space-l)">
            <span aria-hidden="true"><?php echo tq_icon('user-check', 18); ?></span>
            <span>
                <strong><?php echo t('له حساب في المنصة.'); ?></strong>
                <?php echo t('طابق بريده أو جواله حساب'); ?>
                <a href="<?php echo site_url('taqdar_admin/people?q='
                    . rawurlencode(trim((string) ($r['user_email'] ?? $tq_mail)))); ?>">
                    <?php echo html_escape(trim((string) ($r['user_name'] ?? '')) ?: ('#' . (int) $r['user_id'])); ?>
                </a><?php echo t('. فلا يعرض عليه تسجيل جديد — يسأل عن اشتراكه.'); ?>
            </span>
        </div>
    <?php endif; ?>
</div>

<?php /* ══════════ بم أجاب ══════════
         وأسئلة النموذج تعرض كما وردت لا بأسماء نخترعها: صاحب الحملة
         يكتب سؤاله بالعربية، وتسمية من عندنا فوقه تجعل الجواب يقرأ
         على غير سؤاله. */ ?>
<?php if ($tq_extra): ?>
<div class="tqa-card tqa-section">
    <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
        <span class="tqa-iconbox tqa-sky" aria-hidden="true"><?php echo tq_icon('clipboard', 20); ?></span>
        <h2><?php echo t('أجوبته في النموذج'); ?></h2>
    </div>
    <dl class="tqa-fieldgrid" style="margin:0">
        <?php foreach ($tq_extra as $a): ?>
            <div>
                <dt class="tqa-field__label" dir="auto"><?php echo html_escape((string) ($a['name'] ?? '')); ?></dt>
                <dd style="margin:0" dir="auto"><?php
                    echo html_escape(trim((string) ($a['value'] ?? '')) ?: '—'); ?></dd>
            </div>
        <?php endforeach; ?>
    </dl>
</div>
<?php endif; ?>

<?php /* ══════════ من أين جاء ══════════
         وهو ما يسقط أول ما يسقط في النسخ اليدوي: الاسم والجوال ينسخان،
         والحملة والإعلان لا — فلا يعرف أحد أي إعلان يأتي بمن يشتري. */ ?>
<div class="tqa-card tqa-section">
    <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
        <span class="tqa-iconbox tqa-lilac" aria-hidden="true"><?php echo tq_icon('target', 20); ?></span>
        <h2><?php echo t('مصدره الإعلاني'); ?></h2>
    </div>
    <dl class="tqa-fieldgrid" style="margin:0">
        <div>
            <dt class="tqa-field__label"><?php echo t('المنصة'); ?></dt>
            <dd style="margin:0">
                <?php echo html_escape($this->tq_leads->platform_word((string) ($r['platform'] ?? ''))); ?>
                <?php if (!empty($r['is_organic'])): ?>
                    <span class="tqa-badge tqa-badge--muted"><?php echo t('من منشور لا إعلان'); ?></span>
                <?php endif; ?>
            </dd>
        </div>
        <?php
        $tq_src = array(
            array(t('الحملة'),      (string) ($r['campaign_name'] ?? ''), (string) ($r['campaign_id'] ?? '')),
            array(t('المجموعة'),    (string) ($r['adset_name'] ?? ''),    (string) ($r['adset_id'] ?? '')),
            array(t('الإعلان'),     (string) ($r['ad_name'] ?? ''),       (string) ($r['ad_id'] ?? '')),
            array(t('النموذج'),     (string) ($r['form_name'] ?? ''),     (string) ($r['form_id'] ?? '')),
            array(t('الصفحة'),      '',                                   (string) ($r['page_id'] ?? '')),
        );
        foreach ($tq_src as $s): ?>
            <div>
                <dt class="tqa-field__label"><?php echo html_escape($s[0]); ?></dt>
                <dd style="margin:0">
                    <?php if (trim($s[1]) !== ''): ?>
                        <?php echo html_escape($s[1]); ?>
                        <?php if (trim($s[2]) !== ''): ?>
                            <br><span class="tqa-num tqa-dim" dir="ltr"><?php echo html_escape($s[2]); ?></span>
                        <?php endif; ?>
                    <?php elseif (trim($s[2]) !== ''): ?>
                        <span class="tqa-num" dir="ltr"><?php echo html_escape($s[2]); ?></span>
                    <?php else: ?>
                        <span class="tqa-dim">—</span>
                    <?php endif; ?>
                </dd>
            </div>
        <?php endforeach; ?>
        <div>
            <dt class="tqa-field__label"><?php echo t('معرفه عند ميتا'); ?></dt>
            <dd class="tqa-num" style="margin:0" dir="ltr">
                <?php echo html_escape((string) ($r['meta_lead_id'] ?? '')); ?>
            </dd>
        </div>
    </dl>
    <p class="tqa-hint" style="margin-block-start:var(--tq-space-l)">
        <?php echo t('والمعرف فريد في قاعدتنا، فنداء ميتا المكرر لا يكتب هذا العميل مرتين — ولا يعد مرتين في تقرير الحملة.'); ?>
    </p>
</div>

<?php /* ══════════ قرار المتابعة ══════════
         آخر الشاشة لأنه يكتب بعد المكالمة لا قبلها. */ ?>
<div class="tqa-card tqa-section" style="max-inline-size:720px">
    <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
        <span class="tqa-iconbox tqa-peach" aria-hidden="true"><?php echo tq_icon('pen', 20); ?></span>
        <h2><?php echo t('المتابعة'); ?></h2>
    </div>

    <form method="post" action="<?php echo site_url('taqdar_admin/lead_save'); ?>">
        <?php echo tq_csrf(); ?>
        <input type="hidden" name="id" value="<?php echo (int) ($r['id'] ?? 0); ?>">

        <div class="tqa-field">
            <label class="tqa-field__label" for="f_status"><?php echo t('الحال'); ?></label>
            <select class="tqa-input" id="f_status" name="status">
                <?php foreach ($tq_sts as $k => $s): ?>
                    <option value="<?php echo html_escape($k); ?>"
                        <?php echo $tq_st === $k ? 'selected' : ''; ?>>
                        <?php echo html_escape($s['label']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="tqa-field__hint">
                <?php echo t('و«تم التواصل» غير «غير مؤهل»: الأولى تستأنف غدا والثانية قرار ينهي المتابعة — ودمجهما في «متابع» يجعل القائمة لا تقول لأحد ما يفعل.'); ?>
            </span>
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="f_note"><?php echo t('ملاحظة'); ?></label>
            <textarea class="tqa-input" id="f_note" name="note" rows="4"
                      placeholder="<?php echo te('ما قاله في المكالمة، وما وعدت به، ومتى تعاود…'); ?>"><?php
                echo html_escape((string) ($r['note'] ?? '')); ?></textarea>
            <span class="tqa-field__hint">
                <?php echo t('تخرج مع هذا العميل في ملف التصدير، فمن يعاود الاتصال بعد أسبوع لا يبدأ من الصفر.'); ?>
            </span>
        </div>

        <?php if (!empty($r['decided_at'])): ?>
            <p class="tqa-hint">
                <?php echo t('آخر تحديث لحاله:'); ?>
                <span class="tqa-num" dir="ltr"><?php
                    echo html_escape(date('Y-m-d H:i', strtotime((string) $r['decided_at']))); ?></span>
            </p>
        <?php endif; ?>

        <div class="tqa-actions">
            <button type="submit" class="tqa-btn tqa-btn--primary">
                <?php echo tq_icon('check', 16); ?> <?php echo t('احفظ المتابعة'); ?>
            </button>
        </div>
    </form>
</div>
