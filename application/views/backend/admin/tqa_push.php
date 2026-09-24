<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * TQ-PUSH — إشعارات التطبيق (Firebase Cloud Messaging).
 *
 * الترتيب ترتيب السؤال: **أيعمل الاتصال؟** ثم **كم جهازا يستقبل؟** ثم
 * **هل وصل آخر ما أرسل؟** — والمفتاح آخرها: يضبط مرة ويقرأ الباقي كل يوم.
 *
 * **ولا سر يعرض.** المفتاح الخاص لا يطبع أبدا؛ تعرض بصمته (المشروع
 * والبريد وأول معرف المفتاح)، والحقل يترك فارغا فلا يمس.
 */
$tq_fp  = isset($tq_fp) ? $tq_fp : null;
$tq_st  = isset($tq_stats) ? $tq_stats : array();
$tq_dv  = isset($tq_devices) ? $tq_devices : array();
$tq_lg  = isset($tq_log) ? $tq_log : array();
$tq_pr  = isset($tq_probe) && is_array($tq_probe) ? $tq_probe : null;

$tq_name = function ($r) {
    $n = trim((string) (isset($r['first_name']) ? $r['first_name'] : '') . ' '
            . (string) (isset($r['last_name']) ? $r['last_name'] : ''));
    return $n !== '' ? $n : ('#' . (int) $r['user_id']);
};
?>

<?php tqa_head(t('إشعارات التطبيق'),
    t('كل إشعار يكتب داخل المنصة يصل كذلك إلى جوال صاحبه عبر Firebase والتطبيق مغلق. وبلا مفتاح لا شيء يتغير.'),
    'bell'); ?>

<div class="tqa-note <?php echo $tq_fp ? '' : 'tqa-note--warn'; ?> tqa-section">
    <span aria-hidden="true"><?php echo tq_icon($tq_fp ? 'check-badge' : 'alert', 18); ?></span>
    <span>
        <?php if ($tq_fp): ?>
            <?php echo t('متصل بمشروع Firebase:'); ?>
            <strong dir="ltr"><?php echo html_escape($tq_fp['project_id']); ?></strong>
            · <span dir="ltr"><?php echo html_escape($tq_fp['client_email']); ?></span>
            · <?php echo t('معرف المفتاح'); ?> <span dir="ltr"><?php echo html_escape($tq_fp['key_id']); ?>…</span>
        <?php else: ?>
            <strong><?php echo t('لا مفتاح Firebase محفوظ.'); ?></strong>
            <?php echo t('الإشعارات تكتب داخل المنصة كما كانت، ولا يطرق جوال أحد حتى يحفظ المفتاح أسفل الشاشة.'); ?>
        <?php endif; ?>
    </span>
</div>

<?php if ($tq_fp): ?>
<div class="tqa-card tqa-section">
    <h2 class="tqa-card__t">
        <?php echo t('فحص الاتصال'); ?>
        <?php if ($tq_pr): ?>
            <span class="tqa-badge <?php echo $tq_pr['ok'] ? 'tqa-badge--ok' : 'tqa-badge--danger'; ?>">
                <?php echo $tq_pr['ok'] ? t('يعمل') : t('لا يعمل'); ?>
            </span>
        <?php endif; ?>
    </h2>
    <?php if ($tq_pr): ?>
        <div class="tqa-table__wrap">
            <table class="tqa-table">
                <tbody>
                <?php foreach ($tq_pr['steps'] as $tq_s): ?>
                    <tr>
                        <td style="width:32px"><?php echo tq_icon($tq_s['ok'] ? 'check' : 'alert', 18); ?></td>
                        <td><strong><?php echo html_escape(t($tq_s['label'])); ?></strong></td>
                        <td dir="auto"><?php echo html_escape((string) $tq_s['note']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="tqa-field__hint"><?php echo t('فحص في'); ?> <span dir="ltr"><?php echo html_escape((string) $tq_pr['at']); ?></span></p>
    <?php else: ?>
        <p class="tqa-field__hint"><?php echo t('يطلب رمز وصول من جوجل بالمفتاح، ثم يسأل FCM عن المشروع بطلب تحقق لا يصل إلى أحد.'); ?></p>
    <?php endif; ?>
    <div class="tqa-actions">
        <a class="tqa-btn" href="<?php echo site_url('taqdar_admin/push_probe'); ?>">
            <?php echo tq_icon('check-badge', 16); ?> <?php echo t('افحص الاتصال الآن'); ?>
        </a>
    </div>
</div>
<?php endif; ?>

<div class="tqa-grid tqa-grid--4 tqa-section">
    <div class="tqa-stat">
        <div class="tqa-stat__top">
            <span class="tqa-stat__label"><?php echo t('أجهزة مسجلة'); ?></span>
            <span class="tqa-stat__icon tqa-mint" aria-hidden="true"><?php echo tq_icon('bell', 18); ?></span>
        </div>
        <span class="tqa-stat__value"><?php echo (int) $tq_st['devices']; ?></span>
        <span class="tqa-stat__hint">Android <?php echo (int) $tq_st['android']; ?> · iOS <?php echo (int) $tq_st['ios']; ?></span>
    </div>
    <div class="tqa-stat">
        <div class="tqa-stat__top">
            <span class="tqa-stat__label"><?php echo t('حسابات لها جهاز'); ?></span>
            <span class="tqa-stat__icon" aria-hidden="true"><?php echo tq_icon('user-check', 18); ?></span>
        </div>
        <span class="tqa-stat__value"><?php echo (int) $tq_st['users']; ?></span>
    </div>
    <div class="tqa-stat">
        <div class="tqa-stat__top">
            <span class="tqa-stat__label"><?php echo t('أرسل في آخر يوم'); ?></span>
            <span class="tqa-stat__icon tqa-mint" aria-hidden="true"><?php echo tq_icon('check', 18); ?></span>
        </div>
        <span class="tqa-stat__value"><?php echo (int) $tq_st['ok_24h']; ?></span>
    </div>
    <div class="tqa-stat">
        <div class="tqa-stat__top">
            <span class="tqa-stat__label"><?php echo t('تعذر في آخر يوم'); ?></span>
            <span class="tqa-stat__icon" aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
        </div>
        <span class="tqa-stat__value"><?php echo (int) $tq_st['fail_24h']; ?></span>
    </div>
</div>

<?php if ($tq_fp): ?>
<div class="tqa-card tqa-section">
    <h2 class="tqa-card__t"><?php echo t('إرسال إشعار فحص'); ?></h2>
    <p class="tqa-field__hint" style="margin-block-end:10px">
        <?php echo t('إشعار حقيقي يصل إلى الجوال. اختر جهازا مسجلا، أو اكتب بريد حساب فيصل إلى كل أجهزته. والجهاز يظهر هنا بعد أن يسجل صاحبه الدخول من التطبيق.'); ?>
    </p>
    <form method="post" action="<?php echo site_url('taqdar_admin/push_test'); ?>">
        <?php echo tq_csrf(); ?>
        <div class="tqa-field">
            <label class="tqa-field__label" for="p_dev"><?php echo t('الجهاز'); ?></label>
            <select class="tqa-select" id="p_dev" name="device_id">
                <option value="0"><?php echo t('— بالبريد أدناه —'); ?></option>
                <?php foreach ($tq_dv as $tq_d): ?>
                    <option value="<?php echo (int) $tq_d['id']; ?>">
                        <?php echo html_escape($tq_name($tq_d) . ' · ' . $tq_d['platform']
                            . ' · ' . date('Y-m-d H:i', (int) $tq_d['last_seen_at'])); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="tqa-field">
            <label class="tqa-field__label" for="p_mail"><?php echo t('أو بريد الحساب'); ?></label>
            <input class="tqa-input tqa-input--ltr" type="email" dir="ltr" id="p_mail" name="email"
                   autocomplete="off" placeholder="student@example.com">
        </div>
        <div class="tqa-field">
            <label class="tqa-field__label" for="p_t"><?php echo t('العنوان'); ?></label>
            <input class="tqa-input" type="text" id="p_t" name="title" maxlength="120"
                   placeholder="<?php echo te('إشعار فحص من تقدر'); ?>">
        </div>
        <div class="tqa-field">
            <label class="tqa-field__label" for="p_b"><?php echo t('النص'); ?></label>
            <input class="tqa-input" type="text" id="p_b" name="body" maxlength="300"
                   placeholder="<?php echo te('وصوله يعني أن إشعارات التطبيق تعمل.'); ?>">
        </div>
        <div class="tqa-actions">
            <button type="submit" class="tqa-btn tqa-btn--primary">
                <?php echo tq_icon('send', 16); ?> <?php echo t('أرسل الإشعار'); ?>
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="tqa-card tqa-section">
    <h2 class="tqa-card__t"><?php echo t('آخر ما أرسل'); ?></h2>
    <?php if (!$tq_lg): ?>
        <p class="tqa-field__hint"><?php echo t('لم يرسل شيء بعد.'); ?></p>
    <?php else: ?>
        <div class="tqa-table__wrap">
            <table class="tqa-table">
                <thead>
                    <tr><th><?php echo t('إلى'); ?></th><th><?php echo t('الغرض'); ?></th><th><?php echo t('الحال'); ?></th><th><?php echo t('متى'); ?></th></tr>
                </thead>
                <tbody>
                <?php foreach ($tq_lg as $tq_l): ?>
                    <tr>
                        <td><?php echo html_escape($tq_name($tq_l)); ?>
                            <span class="tqa-hint">· <?php echo html_escape((string) $tq_l['platform']); ?></span></td>
                        <td><span dir="ltr"><?php echo html_escape((string) $tq_l['purpose']); ?></span></td>
                        <td>
                            <?php if ((int) $tq_l['ok']): ?>
                                <span class="tqa-badge tqa-badge--ok"><?php echo t('قبله Firebase'); ?></span>
                                <br><span class="tqa-hint" dir="ltr"><?php echo html_escape(basename((string) $tq_l['message_id'])); ?></span>
                            <?php else: ?>
                                <span class="tqa-badge tqa-badge--danger"><?php echo t('تعذر'); ?></span>
                                <br><span class="tqa-hint" dir="auto"><?php echo html_escape((string) $tq_l['error']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td dir="ltr"><?php echo date('Y-m-d H:i:s', (int) $tq_l['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<form method="post" action="<?php echo site_url('taqdar_admin/push_save'); ?>">
    <?php echo tq_csrf(); ?>
    <div class="tqa-card tqa-section">
        <h2 class="tqa-card__t"><?php echo t('مفتاح الخادم — Service Account'); ?></h2>
        <div class="tqa-field">
            <label class="tqa-field__label" for="f_sa"><?php echo t('محتوى ملف JSON كاملا'); ?></label>
            <textarea class="tqa-textarea tqa-input--ltr" id="f_sa" name="sa_json" dir="ltr" rows="6"
                      spellcheck="false" autocomplete="off"
                      placeholder="<?php echo $tq_fp ? te('محفوظ — اتركه فارغا فلا يمس') : '{ "type": "service_account", … }'; ?>"></textarea>
            <span class="tqa-field__hint">
                <?php echo t('من Firebase Console ← Project settings ← Service accounts ← Generate new private key. وليس ملف google-services.json — ذاك للتطبيق. والمفتاح لا يعرض هنا بعد حفظه.'); ?>
            </span>
            <?php if ($tq_fp): ?>
                <label class="tqa-check">
                    <input type="checkbox" name="sa_clear" value="1">
                    <span><?php echo t('امسح المفتاح المحفوظ'); ?></span>
                </label>
            <?php endif; ?>
        </div>
        <div class="tqa-actions">
            <button type="submit" class="tqa-btn tqa-btn--primary">
                <?php echo tq_icon('check', 16); ?> <?php echo t('احفظ وافحص'); ?>
            </button>
        </div>
    </div>
</form>
