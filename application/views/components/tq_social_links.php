<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * TQ-SOCIAL · الحسابات المرتبطة — لوح واحد لبوابات الثلاث.
 *
 * ويجيب سؤالين يسألهما صاحب الحساب:
 *
 *   **بم أدخل؟** — من أنشئ حسابه بجوجل لا يعرف كلمة مرور أصلا
 *   (عشوائية بحكم البناء)، فشاشة تعرض «تغيير كلمة المرور» وحدها تطلب
 *   منه «الحالية» وهو لا يملكها، ولا شيء يقول له أين بابه.
 *
 *   **وكيف أضيف بابا ثانيا؟** — الربط بزر، وفصله يشترط بابا غيره
 *   (TQ-SOCIAL-LASTDOOR): من يفصل ربطه الوحيد بلا كلمة مرور يقفل على
 *   نفسه حسابه واشتراكه، والزر يرد بالسبب لا بـ«غير مسموح».
 *
 * **وبلا مفاتيح لا يطبع شيء**: مزود غير مضبوط لا يعرض ولا يعد بباب لا
 * يفتح — وهي قاعدة `site_social_buttons.php` نفسها.
 */
$tq_sl_ci = &get_instance();
$tq_sl_uid = (int) $tq_sl_ci->session->userdata('user_id');
if ($tq_sl_uid > 0):

$tq_sl_ci->load->model('taqdar_social_model', 'tq_sl_m');
$tq_sl_live  = $tq_sl_ci->tq_sl_m->live();
$tq_sl_rows  = $tq_sl_ci->tq_sl_m->links_of($tq_sl_uid);
$tq_sl_pw    = $tq_sl_ci->tq_sl_m->has_own_password($tq_sl_uid);

if ($tq_sl_live || $tq_sl_rows):

/* المرتبط يعرض ولو أطفئ مزوده: إخفاؤه يجعل صاحبه يقرأ «لا حساب
   مرتبط» وهو يدخل به كل يوم. */
$tq_sl_by = array();
foreach ($tq_sl_rows as $tq_sl_r) { $tq_sl_by[(string) $tq_sl_r['provider']] = $tq_sl_r; }

$tq_sl_all = $tq_sl_live;
foreach ($tq_sl_by as $tq_sl_k => $tq_sl_r) {
    if (!isset($tq_sl_all[$tq_sl_k])) {
        $tq_sl_all[$tq_sl_k] = array(
            'key'   => $tq_sl_k,
            'label' => ($tq_sl_k === 'apple' ? t('أبل') : t('جوجل')),
            'on'    => false,
        );
    }
}
?>
<section class="tq-card">
    <h2 class="tq-card__title"><?php echo t('الحسابات المرتبطة'); ?></h2>
    <p class="tq-caption">
        <?php echo t('حسابات تدخل بها إلى تقدر بنقرة واحدة بدل كلمة المرور.'); ?>
    </p>

    <?php foreach ($tq_sl_all as $tq_sl_k => $tq_sl_p):
        $tq_sl_row  = isset($tq_sl_by[$tq_sl_k]) ? $tq_sl_by[$tq_sl_k] : null;
        /* الفصل يمنع متى كان الباب الوحيد — والزر لا يعرض أصلا حينها:
           زر يرد كل مرة يقرأ عطلا. */
        $tq_sl_last = $tq_sl_row && count($tq_sl_rows) <= 1 && !$tq_sl_pw;
    ?>
        <div class="tq-row tq-row--between tq-sociallink">
            <div class="tq-sociallink__main">
                <strong><?php echo html_escape((string) $tq_sl_p['label']); ?></strong>
                <?php if ($tq_sl_row): ?>
                    <span class="tq-caption" dir="ltr" style="display:block">
                        <?php echo html_escape((string) $tq_sl_row['email']); ?>
                    </span>
                    <?php if (!empty($tq_sl_row['is_relay'])): ?>
                        <?php /* بريد أبل المرحل: صندوق يحول إلى بريده ولا
                                 يكشفه. يقال له ذلك صراحة، وإلا ظن أن
                                 المنصة كتبت بريدا غريبا على حسابه. */ ?>
                        <span class="tq-caption" style="display:block">
                            <?php echo t('بريد مخفي من أبل — رسائلنا تصلك عليه ويحولها إلى بريدك الحقيقي.'); ?>
                        </span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="tq-caption" style="display:block"><?php echo t('غير مرتبط'); ?></span>
                <?php endif; ?>
            </div>

            <div class="tq-sociallink__side">
                <?php if (!$tq_sl_row && !empty($tq_sl_p['on'])): ?>
                    <a class="tq-btn tq-btn--ghost"
                       href="<?php echo site_url('account/social/link/' . $tq_sl_k); ?>">
                        <?php echo t('اربط'); ?>
                    </a>
                <?php elseif ($tq_sl_row && !$tq_sl_last): ?>
                    <form method="post" action="<?php echo site_url('account/social/unlink'); ?>">
                        <?php echo tq_csrf(); ?>
                        <input type="hidden" name="provider" value="<?php echo html_escape($tq_sl_k); ?>">
                        <button class="tq-btn tq-btn--ghost" type="submit"><?php echo t('افصل'); ?></button>
                    </form>
                <?php elseif ($tq_sl_last): ?>
                    <span class="tq-caption"><?php echo t('بابك الوحيد'); ?></span>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (!$tq_sl_pw && $tq_sl_rows): ?>
        <p class="tq-caption" style="margin-block-end:0">
            <?php echo t('لا كلمة مرور على حسابك بعد — دخولك بهذه الحسابات وحدها.'); ?>
            <a href="<?php echo site_url('login/forgot_password_request'); ?>"><?php
                echo t('ضع كلمة مرور من هنا'); ?></a>
            <?php echo t('لتفتح لك بابا ثانيا.'); ?>
        </p>
    <?php endif; ?>
</section>
<?php endif; endif; ?>
