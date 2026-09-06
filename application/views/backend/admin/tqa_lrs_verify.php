<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * اختبار التحقق — الطرف الذي عندنا من الخطوة الثانية.
 *
 * بوابة الجهة (`services.futurex.sa`) تقفل بيانات **الإنتاج** خلف اختبار
 * تحقق يجرى في بيئة الاختبار: يكتب فيه رقم هوية متعلم ومعرف دورة، ثم
 * يبحث في مستودعهم عن رحلة ذلك المتعلم في تلك الدورة — عشر رسائل
 * بترتيبها المنشور.
 *
 * وهذه الشاشة تجهز الرحلة وتطبع **القيمتين اللتين تلصقان في نموذجهم**.
 * فالخطأ الذي تمنعه هو الأشيع: أن يكتب المسؤول معرفا يظنه صحيحا بينما
 * المرسل غيره، فيرد الاختبار «لا بيانات» ولا أحد يعرف السبب.
 */
$tq_r  = is_array($result) ? $result : null;
$tq_ok = $tq_r && !empty($tq_r['ok']);
?>

<?php tqa_head(t('اختبار التحقق'),
    t('يولّد رحلة تعلّم كاملة (عشر رسائل) لمتعلّم ومقرّر — ثم يعطيك ما تكتبه في نموذج الجهة.'),
    'check-badge'); ?>

<?php if (empty($cfg['enabled'])): ?>
<div class="tqa-note tqa-note--warn tqa-section">
    <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
    <span>
        <strong><?php echo t('الربط معطّل.'); ?></strong>
        <?php echo t('الرحلة لن تولّد ما دام مطفأ — فعّله من'); ?>
        <a href="<?php echo site_url('taqdar_admin/lrs'); ?>"><?php echo t('شاشة المستودع'); ?></a>.
    </span>
</div>
<?php endif; ?>

<div class="tqa-card" style="max-inline-size:760px">
    <form method="post" action="<?php echo site_url('taqdar_admin/lrs_verify_run'); ?>">
        <?php echo tq_csrf(); ?>

        <div class="tqa-field">
            <label class="tqa-field__label" for="v_nid"><?php echo t('رقم الهوية'); ?> *</label>
            <input class="tqa-input tqa-input--ltr" type="text" id="v_nid" name="nid"
                   dir="ltr" inputmode="numeric" maxlength="10"
                   value="<?php echo html_escape($nid); ?>" required>
            <p class="tqa-field__hint">
                <?php echo t('هويّة متعلّم مسجّل في المنصّة — عشرة أرقام. ومن لا هويّة له يكتبها من «إعداداتي ← بيانات الملف».'); ?>
            </p>
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="v_course"><?php echo t('المقرّر'); ?> *</label>
            <select class="tqa-input" id="v_course" name="course_id" required>
                <option value=""><?php echo t('— اختر مقرّرًا —'); ?></option>
                <?php foreach ($courses as $tq_c): ?>
                    <option value="<?php echo (int) $tq_c['course_id']; ?>"
                        <?php echo (int) $tq_c['course_id'] === (int) $course_id ? 'selected' : ''; ?>>
                        <?php echo html_escape($tq_c['title']); ?>
                        <?php echo $tq_c['grade'] ? ' — ' . html_escape($tq_c['grade']) : ''; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="tqa-field__hint">
                <?php echo t('المقرّر الذي تُبنى عليه الرحلة — ورابطه هو «معرّف الدورة» الذي تكتبه عندهم.'); ?>
            </p>
        </div>

        <label class="tqa-check">
            <input type="checkbox" name="force" value="1">
            <span><?php echo t('أعد التوليد من جديد (يمحو رحلة سابقة لهذا المتعلّم في هذا المقرّر)'); ?></span>
        </label>

        <div style="margin-block-start:var(--tq-space-l)">
            <button class="tqa-btn tqa-btn--primary" type="submit"><?php echo t('بدء الاختبار'); ?></button>
        </div>
    </form>
</div>

<?php /* ── ما يُلصق في نموذج الجهة ──────────────────────────────── */ ?>
<?php if ($tq_ok): ?>
<div class="tqa-card tqa-section" style="max-inline-size:760px">
    <div class="tqa-card__head" style="padding:0 0 var(--tq-space-m);margin-block-end:var(--tq-space-m)">
        <span class="tqa-iconbox tqa-mint" aria-hidden="true"><?php echo tq_icon('copy', 20); ?></span>
        <h2><?php echo t('اكتب هذين في نموذج الجهة'); ?></h2>
    </div>

    <p class="tqa-hint">
        <?php echo t('في'); ?>
        <span class="tq-ltr">services.futurex.sa</span> ←
        <?php echo t('«تشغيل اختبار التحقق». وانسخهما كما هما — حرفًا بحرف.'); ?>
    </p>

    <div class="tqa-field">
        <label class="tqa-field__label"><?php echo t('رقم الهوية'); ?></label>
        <input class="tqa-input tqa-input--ltr" type="text" dir="ltr" readonly
               onclick="this.select()"
               value="<?php echo html_escape($tq_r['actor']); ?>">
    </div>

    <div class="tqa-field">
        <label class="tqa-field__label"><?php echo t('معرّف الدورة (Course ID)'); ?></label>
        <input class="tqa-input tqa-input--ltr" type="text" dir="ltr" readonly
               onclick="this.select()"
               value="<?php echo html_escape($tq_r['object']); ?>">
        <p class="tqa-field__hint">
            <?php echo t('وهو رابط يفتح فعلًا — افتحه لتتأكّد.'); ?>
            <a href="<?php echo html_escape($tq_r['object']); ?>" target="_blank" rel="noopener"><?php echo t('افتحه'); ?></a>
        </p>
    </div>
</div>
<?php endif; ?>

<?php /* ── حال الرحلة ───────────────────────────────────────────── */ ?>
<?php if ($steps): ?>
<div class="tqa-card tqa-section">
    <div class="tqa-card__head" style="padding:0 0 var(--tq-space-m);margin-block-end:var(--tq-space-m)">
        <span class="tqa-iconbox tqa-mint" aria-hidden="true"><?php echo tq_icon('send', 20); ?></span>
        <h2><?php echo t('الرحلة'); ?> — <span class="tq-ltr"><?php echo count($steps); ?></span> <?php echo t('رسائل'); ?></h2>
    </div>

    <p class="tqa-hint">
        <?php echo t('ما دام المستودع محجوبًا تبقى الرسائل «في الانتظار» ولا تضيع — وأوّل جولة بعد فتح الحجب تدفعها كلّها، ثم يجدها اختبارهم.'); ?>
    </p>

    <div style="overflow-x:auto">
        <table class="tqa-table">
            <thead><tr>
                <th><?php echo t('#'); ?></th>
                <th><?php echo t('الفعل'); ?></th>
                <th><?php echo t('الحال'); ?></th>
                <th><?php echo t('محاولات'); ?></th>
                <th><?php echo t('العلة'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($steps as $tq_i => $tq_s): ?>
                <tr>
                    <td class="tq-ltr"><?php echo $tq_i + 1; ?></td>
                    <td class="tq-ltr"><?php echo html_escape($tq_s['verb']); ?></td>
                    <td><?php echo html_escape($tq_s['state']); ?></td>
                    <td class="tq-ltr"><?php echo (int) $tq_s['attempts']; ?></td>
                    <td><?php echo html_escape(mb_substr((string) $tq_s['last_error'], 0, 80)); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
