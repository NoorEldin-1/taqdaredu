<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * مستودع سجلات التعلم الوطني (NELC) — شاشة الربط.
 *
 * وتعرض ثلاثة لا واحدا، لأن كلا منها يخفق وحده:
 *   ١ — **الإعدادات**: أمحفوظة؟
 *   ٢ — **حال الاتصال**: أتصل فعلا؟ (محفوظ لا يعني يصل)
 *   ٣ — **حال الطابور**: أخرج ما تجمع؟ (يصل لا يعني لا متراكم)
 *
 * وكلمة المرور لا تخرج إلى هنا أبدا: الحقل يترك خاليا للإبقاء عليها،
 * والمسح صريح بمربع — كما في واتساب والبريد.
 */
$tq_st  = $stats;
$tq_h   = is_array($health) ? $health : null;
$tq_cnt = (int) $tq_st['total'];
?>

<?php tqa_head(t('مستودع سجلات التعلم'),
    t('يرسل رحلة المتعلم إلى مستودع المركز الوطني للتعلم الإلكتروني بمعيار xAPI — شرط الاعتماد.'),
    'send'); ?>

<?php /* الحال أولا: من يفتح الشاشة يريد أن يعرف أيخرج شيء الآن أم لا. */ ?>
<div class="tqa-note <?php echo $configured ? '' : 'tqa-note--warn'; ?> tqa-section">
    <span aria-hidden="true"><?php echo tq_icon($configured ? 'check-badge' : 'alert', 18); ?></span>
    <span>
        <?php if ($configured): ?>
            <strong><?php echo t('الربط مهيأ.'); ?></strong>
            <?php echo t('الأحداث تشتق كل خمس دقائق وتخرج تباعا. واضغط «افحص الاتصال» لتعرف أيصل فعلا.'); ?>
        <?php else: ?>
            <strong><?php echo t('لا يخرج شيء الآن.'); ?></strong>
            <?php echo t('ينقص:'); ?> <?php echo html_escape(implode(' · ', $missing)); ?>.
            <?php echo t('والأحداث تتجمع في الطابور ولا تضيع.'); ?>
        <?php endif; ?>
    </span>
</div>

<?php if ($tq_h): ?>
<div class="tqa-note <?php echo !empty($tq_h['ok']) ? '' : 'tqa-note--warn'; ?> tqa-section">
    <span aria-hidden="true"><?php echo tq_icon(!empty($tq_h['ok']) ? 'check-badge' : 'alert', 18); ?></span>
    <span>
        <strong><?php echo t('حال الاتصال:'); ?></strong>
        <?php echo html_escape($tq_h['note']); ?>
        <?php if ((int) $tq_h['code'] > 0): ?>
            <span class="tq-ltr">(<?php echo (int) $tq_h['code']; ?>)</span>
        <?php endif; ?>
    </span>
</div>
<?php endif; ?>

<?php /* ── الطابور ────────────────────────────────────────────────── */ ?>
<div class="tqa-card tqa-section">
    <div class="tqa-card__head" style="padding:0 0 var(--tq-space-m);margin-block-end:var(--tq-space-m)">
        <span class="tqa-iconbox tqa-mint" aria-hidden="true"><?php echo tq_icon('send', 20); ?></span>
        <h2><?php echo t('الطابور'); ?></h2>
    </div>
    <p class="tqa-hint">
        <?php echo t('كل حدث يكتب هنا أولا ثم يرسل. فما لم يصل لا يضيع — يعاد إرساله.'); ?>
    </p>

    <div style="display:flex;flex-wrap:wrap;gap:var(--tq-space-m);margin-block-end:var(--tq-space-l)">
        <?php
        $tq_cells = array(
            'queued'  => array(t('في الانتظار'), 'clock'),
            'sent'    => array(t('أرسلت'),        'check-badge'),
            'dead'    => array(t('توقفت'),        'alert'),
            'skipped' => array(t('متروكة'),       'minus'),
        );
        foreach ($tq_cells as $tq_k => $tq_m): ?>
        <div class="tqa-stat" style="flex:1 1 120px">
            <span class="tqa-stat__label tq-ltr" style="font-size:1.6rem;font-weight:700"><?php echo (int) $tq_st[$tq_k]; ?></span>
            <span class="tqa-stat__label"><?php echo html_escape($tq_m[0]); ?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <form method="post" action="<?php echo site_url('taqdar_admin/lrs_test'); ?>"
          style="display:inline-block">
        <?php echo tq_csrf(); ?>
        <button class="tqa-btn" type="submit"><?php echo t('افحص الاتصال'); ?></button>
    </form>

    <?php /* «أعد المحاولة» يعيد المعلق والميت معا — وهو الزر الذي يضغط
             يوم تفتح الجهة الحجب: يتدفق كل ما تجمع بلا نشر شيفرة. */ ?>
    <form method="post" action="<?php echo site_url('taqdar_admin/lrs_retry'); ?>"
          style="display:inline-block">
        <?php echo tq_csrf(); ?>
        <button class="tqa-btn tqa-btn--ghost" type="submit">
            <?php echo t('أعد كل ما توقف إلى الطابور'); ?>
        </button>
    </form>
</div>

<?php /* ── الإعدادات ──────────────────────────────────────────────── */ ?>
<div class="tqa-card" style="max-inline-size:760px">
    <form method="post" action="<?php echo site_url('taqdar_admin/lrs_save'); ?>">
        <?php echo tq_csrf(); ?>

        <div class="tqa-field">
            <label class="tqa-check">
                <input type="checkbox" name="tq_lrs_enabled" value="1"
                       <?php echo !empty($cfg['enabled']) ? 'checked' : ''; ?>>
                <span><?php echo t('فعّل الإرسال إلى المستودع'); ?></span>
            </label>
            <p class="tqa-field__hint">
                <?php echo t('إطفاؤه يوقف الاشتقاق والإرسال معا، ولا يمس المنصة في شيء.'); ?>
            </p>
        </div>

        <?php
        $tq_f = array(
            'tq_lrs_endpoint' => array(t('عنوان المستودع'),
                t('ينتهي بـ /xapi/statements — تعطيه الجهة عند التهيئة.'), 'ltr', $cfg['endpoint']),
            'tq_lrs_user' => array(t('اسم المستخدم'),
                t('اعتماد أساسي (Basic) تصدره الجهة.'), 'ltr', $cfg['user']),
            'tq_lrs_platform' => array(t('معرّف المنصة'),
                t('يذهب في خانة platform من كل رسالة — وهو غير اسم المنصة أدناه.'), 'ltr', $cfg['platform']),
            'tq_lrs_lms_url' => array(t('عنوان المنصة'),
                t('تبنى منه روابط المقررات والدروس في الرسائل. يكتب كاملا بشرطة أخيرة.'), 'ltr', $cfg['lms_url']),
            'tq_lrs_name_ar' => array(t('اسم المنصة بالعربية'),
                t('يظهر في تقارير الجهة.'), 'rtl', $cfg['name_ar']),
            'tq_lrs_name_en' => array(t('اسم المنصة بالإنجليزية'),
                t('ويرسل مع العربي في كل رسالة — الجهة تطلب الاثنين.'), 'ltr', $cfg['name_en']),
        );
        foreach ($tq_f as $tq_k => $tq_m): ?>
        <div class="tqa-field">
            <label class="tqa-field__label" for="f_<?php echo $tq_k; ?>">
                <?php echo html_escape($tq_m[0]); ?>
            </label>
            <input class="tqa-input<?php echo $tq_m[2] === 'ltr' ? ' tqa-input--ltr' : ''; ?>"
                   type="text" id="f_<?php echo $tq_k; ?>" name="<?php echo $tq_k; ?>"
                   dir="<?php echo $tq_m[2]; ?>"
                   value="<?php echo html_escape((string) $tq_m[3]); ?>">
            <p class="tqa-field__hint"><?php echo html_escape($tq_m[1]); ?></p>
        </div>
        <?php endforeach; ?>

        <?php /* السر: خال يعني «أبقِ المحفوظ»، والمسح صريح. */ ?>
        <div class="tqa-field">
            <label class="tqa-field__label" for="f_pass"><?php echo t('كلمة المرور'); ?></label>
            <input class="tqa-input tqa-input--ltr" type="password" id="f_pass"
                   name="tq_lrs_pass" dir="ltr" autocomplete="new-password"
                   placeholder="<?php echo $has_pass ? '••••••••  ' . te('محفوظة — اتركه خاليا للإبقاء عليها') : te('لم تحفظ بعد'); ?>">
            <p class="tqa-field__hint">
                <?php echo t('لا تعرض هنا أبدا. اتركه خاليا لتبقى كما هي.'); ?>
            </p>
            <?php if ($has_pass): ?>
            <label class="tqa-check">
                <input type="checkbox" name="tq_lrs_pass_clear" value="1">
                <span><?php echo t('امسح كلمة المرور المحفوظة'); ?></span>
            </label>
            <?php endif; ?>
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="f_lang"><?php echo t('لغة الرسائل'); ?></label>
            <select class="tqa-input" id="f_lang" name="tq_lrs_lang">
                <option value="ar-SA" <?php echo $cfg['lang'] === 'ar-SA' ? 'selected' : ''; ?>>ar-SA</option>
                <option value="en-US" <?php echo $cfg['lang'] === 'en-US' ? 'selected' : ''; ?>>en-US</option>
            </select>
            <p class="tqa-field__hint">
                <?php echo t('لغة أسماء المقررات والدروس داخل الرسالة. وأسماء الأفعال بالإنجليزية دائما — هكذا يشترط المعيار.'); ?>
            </p>
        </div>

        <button class="tqa-btn tqa-btn--primary" type="submit"><?php echo t('احفظ'); ?></button>
    </form>
</div>

<?php /* ── آخر الرسائل ───────────────────────────────────────────── */ ?>
<?php if ($recent): ?>
<div class="tqa-card tqa-section">
    <div class="tqa-card__head" style="padding:0 0 var(--tq-space-m);margin-block-end:var(--tq-space-m)">
        <span class="tqa-iconbox tqa-mint" aria-hidden="true"><?php echo tq_icon('file-text', 20); ?></span>
        <h2><?php echo t('آخر الرسائل'); ?></h2>
    </div>
    <p class="tqa-hint">
        <?php echo t('جسم الرسالة لا يعرض هنا: فيه بريد المتعلم وهويته، ولا حاجة إليهما لقراءة الحال.'); ?>
    </p>
    <div style="overflow-x:auto">
        <table class="tqa-table">
            <thead><tr>
                <th><?php echo t('الفعل'); ?></th>
                <th><?php echo t('المصدر'); ?></th>
                <th><?php echo t('الحال'); ?></th>
                <th><?php echo t('محاولات'); ?></th>
                <th><?php echo t('العلة'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($recent as $tq_r): ?>
                <tr>
                    <td class="tq-ltr"><?php echo html_escape($tq_r['verb']); ?></td>
                    <td class="tq-ltr"><?php echo html_escape($tq_r['source']); ?></td>
                    <td><?php echo html_escape($tq_r['state']); ?></td>
                    <td class="tq-ltr"><?php echo (int) $tq_r['attempts']; ?></td>
                    <td><?php echo html_escape(mb_substr((string) $tq_r['last_error'], 0, 90)); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
