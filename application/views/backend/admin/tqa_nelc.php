<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * التكامل مع المركز الوطني للتعليم الإلكتروني — TQ-NELC.
 *
 * الشاشة تعرض ولا تحكم: القواعد كلها في `Taqdar_nelc_model`.
 *
 * وترتيبها ترتيب السؤال الذي يفتحها لا ترتيب الجداول:
 *
 *   ١ ــ **أيعمل؟** الحالة أولا، ثم زر يفحصها بالفعل لا بالظن.
 *   ٢ ــ **وعمن يبلغ؟** تغطية الهوية — تكامل مضبوط وطابور فارغ قد يعني
 *        أن لا أحد كتب هويته، لا أن لا أحد درس. ورقم لا يقال يجعل
 *        المسؤول يفتش في الشيفرة عن عطل ليس فيها.
 *   ٣ ــ **بأي مفاتيح؟** الضبط.
 *   ٤ ــ **وماذا خرج؟** الطابور بأخطائه مترجمة.
 *
 * وكلمة السر لا تطبع أبدا — لا في القيمة ولا في `placeholder`. الحقل
 * يترك فارغا فلا تمس، ويكتب فيه فتبدل: شاشة تعرض السر تجعل نسخة ثانية
 * منه في كل لقطة شاشة وكل جلسة مشاركة.
 */
$tq_c   = isset($cfg) ? $cfg : array();
$tq_s   = isset($stats) ? $stats : array();
$tq_cov = isset($coverage) ? $coverage : array();
$tq_ok  = !empty($ready);
$tq_rows  = isset($rows) ? $rows : array();
$tq_state = isset($state) ? $state : '';

$tq_verbs = array(
    'registered'  => t('تسجيل'),
    'initialized' => t('بدء'),
    'watched'     => t('مشاهدة'),
    'completed'   => t('إتمام'),
    'progressed'  => t('تقدم'),
    'attempted'   => t('محاولة'),
    'earned'      => t('شهادة'),
    'rated'       => t('تقييم'),
);

$tq_states = array(
    'queued' => array(t('ينتظر'),   'tqa-badge--info'),
    'sent'   => array(t('وصلت'),    'tqa-badge--ok'),
    'failed' => array(t('فشلت'),    'tqa-badge--danger'),
);
?>

<?php tqa_head(t('المركز الوطني للتعليم الإلكتروني'),
    t('يبلغ المركز بنشاط المتعلمين بمعيار xAPI — وهو شرط ترخيص المنصة.'),
    'shield'); ?>

<?php /* ١ — أيعمل؟ */ ?>
<div class="tqa-note <?php echo $tq_ok ? '' : 'tqa-note--warn'; ?> tqa-section">
    <span aria-hidden="true"><?php echo tq_icon($tq_ok ? 'check-badge' : 'alert', 18); ?></span>
    <span>
        <?php if ($tq_ok): ?>
            <strong><?php echo t('الإبلاغ يعمل.'); ?></strong>
            <?php echo t('كل حدث تعلم يكتب في الطابور، ويفرغه الكرون كل خمس دقائق.'); ?>
        <?php else: ?>
            <strong><?php echo t('الإبلاغ متوقف.'); ?></strong>
            <?php echo t('لا جملة تخرج ولا صف يكتب، وتعمل المنصة كما كانت حرفا بحرف. أكمل رابط النقطة واسم المستخدم وكلمة السر ثم اشعل المفتاح.'); ?>
        <?php endif; ?>
    </span>
</div>

<div class="tqa-grid tqa-grid--4 tqa-section">
    <div class="tqa-stat">
        <div class="tqa-stat__top">
            <span class="tqa-stat__label"><?php echo t('ينتظر الإرسال'); ?></span>
            <span class="tqa-stat__icon tqa-sky" aria-hidden="true"><?php echo tq_icon('refresh', 18); ?></span>
        </div>
        <span class="tqa-stat__value"><?php echo (int) ($tq_s['queued'] ?? 0); ?></span>
    </div>

    <div class="tqa-stat">
        <div class="tqa-stat__top">
            <span class="tqa-stat__label"><?php echo t('وصلت'); ?></span>
            <span class="tqa-stat__icon tqa-mint" aria-hidden="true"><?php echo tq_icon('check-badge', 18); ?></span>
        </div>
        <span class="tqa-stat__value"><?php echo (int) ($tq_s['sent'] ?? 0); ?></span>
    </div>

    <div class="tqa-stat<?php echo (int) ($tq_s['failed'] ?? 0) > 0 ? ' tqa-stat--danger' : ''; ?>">
        <div class="tqa-stat__top">
            <span class="tqa-stat__label"><?php echo t('فشلت نهائيا'); ?></span>
            <span class="tqa-stat__icon tqa-rose" aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
        </div>
        <span class="tqa-stat__value"><?php echo (int) ($tq_s['failed'] ?? 0); ?></span>
    </div>

    <?php /* ٢ — وعمن يبلغ؟ */ ?>
    <div class="tqa-stat<?php echo (int) ($tq_cov['percent'] ?? 0) < 100 ? ' tqa-stat--warn' : ''; ?>">
        <div class="tqa-stat__top">
            <span class="tqa-stat__label"><?php echo t('طلاب لهم رقم هوية'); ?></span>
            <span class="tqa-stat__icon tqa-amber" aria-hidden="true"><?php echo tq_icon('users', 18); ?></span>
        </div>
        <span class="tqa-stat__value"><?php echo (int) ($tq_cov['percent'] ?? 0); ?>%</span>
        <span class="tqa-stat__hint">
            <?php echo (int) ($tq_cov['with_id'] ?? 0); ?>
            <?php echo t('من'); ?>
            <?php echo (int) ($tq_cov['students'] ?? 0); ?>
        </span>
    </div>
</div>

<div class="tqa-note tqa-section">
    <span aria-hidden="true"><?php echo tq_icon('help', 18); ?></span>
    <span>
        <strong><?php echo t('المركز يعرف المتعلم برقم هويته الوطنية، لا ببريده.'); ?></strong>
        <?php echo t('فالطالب الذي لم يكتب رقم هويته لا يبلغ عنه شيء أصلا — ولا ترسل عنه جملة ناقصة: بيانات صحيحة قليلة تراجع، وبيانات مخترعة كثيرة ترد المراجعة كلها. والحقل يكتبه الطالب في إعداداته، أو المسؤول من صفحة الحساب.'); ?>
    </span>
</div>

<?php /* ٣ — بأي مفاتيح؟ */ ?>
<div class="tqa-card tqa-section" style="max-inline-size:820px">
    <form method="post" action="<?php echo site_url('taqdar_admin/nelc_save'); ?>">
        <?php echo tq_csrf(); ?>

        <label class="tqa-check" style="margin-block-end:var(--tq-space-l)">
            <input type="checkbox" name="tq_nelc_enabled" value="1"
                   <?php echo !empty($tq_c['enabled']) ? 'checked' : ''; ?>>
            <span><?php echo t('شغل الإبلاغ إلى المركز'); ?></span>
        </label>

        <div class="tqa-field">
            <label class="tqa-field__label" for="f_ep"><?php echo t('رابط النقطة'); ?></label>
            <input class="tqa-input tqa-input--ltr" type="text" id="f_ep" dir="ltr"
                   name="tq_nelc_endpoint" spellcheck="false" autocomplete="off"
                   value="<?php echo html_escape($tq_c['endpoint'] ?? ''); ?>">
            <span class="tqa-field__hint">
                <?php echo t('بيئة الترخيص التجريبية هي ما يسلم أولا، ثم يعطي المركز نطاق الإنتاج بعد المراجعة. التجريبي:'); ?>
                <code dir="ltr"><?php echo Taqdar_nelc_model::DEFAULT_ENDPOINT; ?></code>
                — <?php echo t('والإنتاج:'); ?>
                <code dir="ltr"><?php echo Taqdar_nelc_model::LIVE_ENDPOINT; ?></code>
            </span>
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="f_us"><?php echo t('اسم المستخدم'); ?></label>
            <input class="tqa-input tqa-input--ltr" type="text" id="f_us" dir="ltr"
                   name="tq_nelc_username" spellcheck="false" autocomplete="off"
                   value="<?php echo html_escape($tq_c['username'] ?? ''); ?>">
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="f_pw"><?php echo t('كلمة السر'); ?></label>
            <input class="tqa-input tqa-input--ltr" type="password" id="f_pw" dir="ltr"
                   name="tq_nelc_password" autocomplete="new-password" value="">
            <span class="tqa-field__hint">
                <?php echo !empty($tq_c['password'])
                    ? t('كلمة السر محفوظة. اترك الحقل فارغا فلا تمس، واكتب فيه لتبدلها.')
                    : t('لم تحفظ كلمة سر بعد — والإبلاغ لا يعمل بدونها.'); ?>
            </span>
        </div>

        <div class="tqa-section"></div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="f_pid"><?php echo t('معرف المنصة'); ?></label>
            <input class="tqa-input tqa-input--ltr" type="text" id="f_pid" dir="ltr"
                   name="tq_nelc_platform_id"
                   value="<?php echo html_escape($tq_c['platform_id'] ?? ''); ?>">
            <span class="tqa-field__hint"><?php echo t('كما سجل عند المركز — يدخل في سياق كل جملة.'); ?></span>
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="f_par"><?php echo t('اسم المنصة بالعربية'); ?></label>
            <input class="tqa-input" type="text" id="f_par" name="tq_nelc_platform_ar"
                   value="<?php echo html_escape($tq_c['platform_ar'] ?? ''); ?>">
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="f_pen"><?php echo t('اسم المنصة بالإنجليزية'); ?></label>
            <input class="tqa-input tqa-input--ltr" type="text" id="f_pen" dir="ltr"
                   name="tq_nelc_platform_en"
                   value="<?php echo html_escape($tq_c['platform_en'] ?? ''); ?>">
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="f_lms"><?php echo t('رابط المنصة'); ?></label>
            <input class="tqa-input tqa-input--ltr" type="text" id="f_lms" dir="ltr"
                   name="tq_nelc_lms_url"
                   value="<?php echo html_escape($tq_c['lms_url'] ?? ''); ?>">
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="f_fb"><?php echo t('بريد يمثل المنصة'); ?></label>
            <input class="tqa-input tqa-input--ltr" type="text" id="f_fb" dir="ltr"
                   name="tq_nelc_fallback_email"
                   value="<?php echo html_escape($tq_c['fallback'] ?? ''); ?>">
            <span class="tqa-field__hint"><?php echo t('يستعمل معلما في جمل الكورسات التي لا صاحب لها — والمعلم حقل إلزامي في كل جملة، فبلا بديل تسقط الجملة كلها لغياب اسم.'); ?></span>
        </div>

        <div class="tqa-section"></div>

        <p class="tqa-field__label" style="margin-block-end:var(--tq-space-s)"><?php echo t('ما يبلغ به'); ?></p>
        <p class="tqa-field__hint" style="margin-block-end:var(--tq-space-m)">
            <?php echo t('كلها مشعلة افتراضا. وتطفأ حين يطلب المركز في المراجعة أن يقتصر على بعضها.'); ?>
        </p>

        <?php foreach (Taqdar_nelc_model::$EVENTS as $tq_k => $tq_ev): ?>
            <label class="tqa-check" style="display:block;margin-block-end:var(--tq-space-s)">
                <input type="checkbox" name="tq_nelc_ev_<?php echo $tq_k; ?>" value="1"
                       <?php echo !empty($tq_c['events'][$tq_k]) ? 'checked' : ''; ?>>
                <span>
                    <strong><?php echo html_escape(t($tq_ev['label'])); ?></strong>
                    <span class="tqa-field__hint" style="display:block">
                        <?php echo html_escape(t($tq_ev['hint'])); ?>
                    </span>
                </span>
            </label>
        <?php endforeach; ?>

        <div class="tqa-actions">
            <button type="submit" class="tqa-btn tqa-btn--primary">
                <?php echo tq_icon('check', 16); ?> <?php echo t('احفظ الضبط'); ?>
            </button>
        </div>
    </form>

    <?php /* أزرار الفعل خارج نموذج الحفظ: زر داخله يرسل الحقول معه، فيصير
             «افحص الاتصال» حفظا لم يطلبه أحد. */ ?>
    <div class="tqa-actions" style="gap:var(--tq-space-s)">
        <form method="post" action="<?php echo site_url('taqdar_admin/nelc_test'); ?>" style="display:inline">
            <?php echo tq_csrf(); ?>
            <button type="submit" class="tqa-btn tqa-btn--ghost" <?php echo $tq_ok ? '' : 'disabled'; ?>>
                <?php echo tq_icon('send', 16); ?> <?php echo t('افحص الاتصال'); ?>
            </button>
        </form>

        <form method="post" action="<?php echo site_url('taqdar_admin/nelc_drain'); ?>" style="display:inline">
            <?php echo tq_csrf(); ?>
            <button type="submit" class="tqa-btn tqa-btn--ghost" <?php echo $tq_ok ? '' : 'disabled'; ?>>
                <?php echo tq_icon('refresh', 16); ?> <?php echo t('أرسل ما ينتظر الآن'); ?>
            </button>
        </form>

        <?php if ((int) ($tq_s['failed'] ?? 0) > 0): ?>
            <form method="post" action="<?php echo site_url('taqdar_admin/nelc_requeue'); ?>" style="display:inline">
                <?php echo tq_csrf(); ?>
                <button type="submit" class="tqa-btn tqa-btn--ghost">
                    <?php echo tq_icon('refresh', 16); ?> <?php echo t('أعد الفاشل إلى الطابور'); ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php /* ٤ — وماذا خرج؟ */ ?>
<section>
    <h2 style="margin-block-end:var(--tq-space-m)"><?php echo t('آخر ما خرج'); ?></h2>

    <div class="tqa-actions" style="gap:var(--tq-space-s);margin-block-end:var(--tq-space-m)">
        <?php foreach (array('' => t('الكل'), 'queued' => t('ينتظر'),
                             'failed' => t('فشل'), 'sent' => t('وصل')) as $tq_f => $tq_lb): ?>
            <a class="tqa-btn tqa-btn--sm <?php echo $tq_state === $tq_f ? 'tqa-btn--primary' : 'tqa-btn--ghost'; ?>"
               href="<?php echo site_url('taqdar_admin/nelc') . ($tq_f !== '' ? '?state=' . $tq_f : ''); ?>">
                <?php echo html_escape($tq_lb); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (!$tq_rows): ?>
        <?php tqa_empty(
            t('لا شيء في الطابور'),
            $tq_ok
                ? t('يمتلئ من نفسه مع أول طالب يفتح درسا أو يسلم اختبارا — على أن يكون له رقم هوية.')
                : t('الإبلاغ متوقف، فلا يكتب صف واحد. اضبط المفاتيح أعلاه أولا.'),
            '', '', 'shield'); ?>
    <?php else: ?>
        <div class="tqa-table__wrap">
        <table class="tqa-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?php echo t('الحدث'); ?></th>
                    <th><?php echo t('المتعلم'); ?></th>
                    <th><?php echo t('الحال'); ?></th>
                    <th><?php echo t('محاولات'); ?></th>
                    <th><?php echo t('الرد'); ?></th>
                    <th><?php echo t('متى'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tq_rows as $tq_r): ?>
                <?php
                $tq_st = (string) $tq_r['state'];
                $tq_bd = isset($tq_states[$tq_st]) ? $tq_states[$tq_st] : array($tq_st, 'tqa-badge--muted');
                $tq_nm = trim((string) $tq_r['first_name'] . ' ' . (string) $tq_r['last_name']);
                ?>
                <tr>
                    <td><?php echo (int) $tq_r['id']; ?></td>
                    <td>
                        <?php echo html_escape($tq_verbs[$tq_r['verb']] ?? $tq_r['verb']); ?>
                        <span class="tqa-field__hint" style="display:block" dir="ltr">
                            <?php echo html_escape($tq_r['event_key']); ?>
                        </span>
                    </td>
                    <td>
                        <?php echo html_escape($tq_nm !== '' ? $tq_nm : '#' . (int) $tq_r['user_id']); ?>
                    </td>
                    <td><span class="tqa-badge <?php echo $tq_bd[1]; ?>"><?php echo html_escape($tq_bd[0]); ?></span></td>
                    <td><?php echo (int) $tq_r['attempts']; ?></td>
                    <td>
                        <?php if ((int) $tq_r['http_code'] > 0): ?>
                            <code dir="ltr"><?php echo (int) $tq_r['http_code']; ?></code>
                        <?php endif; ?>
                        <?php if (!empty($tq_r['last_error'])): ?>
                            <span class="tqa-field__hint" style="display:block">
                                <?php echo html_escape($tq_r['last_error']); ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo html_escape(date('Y-m-d H:i',
                            (int) ($tq_r['sent_at'] ?: $tq_r['created_at']))); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>
