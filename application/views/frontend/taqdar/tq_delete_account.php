<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * تأكيد حذف الحساب.
 *
 * الحذف هنا **تجهيل لا محو**: تستبدل بيانات الهوية بقيم مجهولة وتبقى
 * الفواتير بمعرف مجهول لأن الالتزام الضريبي يوجب حفظها. ويقال ذلك
 * للمستخدم صراحة قبل أن يضغط — لا بعد.
 */
$tq_nav   = 'settings';
$tq_role  = 'student';
$tq_title = t('حذف الحساب');
$tq_sub   = t('إجراء لا رجعة فيه — اقرأ ما يحدث بالضبط');
$tq_icon  = 'lock';

include 'portal_open.php';
?>

<div class="tq-cols tq-cols--only" style="max-inline-size:720px">
    <div class="tq-stack">
        <section class="tq-card">
            <h2 class="tq-card__title"><?php echo t('ماذا يحدث عند الحذف'); ?></h2>
            <dl class="tq-s-list">
                <div class="tq-s-row">
                    <dt class="tq-strong"><?php echo t('يجهل'); ?></dt>
                    <dd class="tq-caption" style="margin:0"><?php echo t('اسمك وبريدك وجوالك وعنوانك وصورتك ونبذتك — تستبدل بقيم مجهولة.'); ?></dd>
                </div>
                <div class="tq-s-row">
                    <dt class="tq-strong"><?php echo t('يغلق'); ?></dt>
                    <dd class="tq-caption" style="margin:0"><?php echo t('لن تستطيع الدخول بعدها، ولا استعادة الحساب.'); ?></dd>
                </div>
                <div class="tq-s-row">
                    <dt class="tq-strong"><?php echo t('يبقى'); ?></dt>
                    <dd class="tq-caption" style="margin:0">
                        <?php echo t('الفواتير والقيود المالية — بمعرف مجهول لا يدل عليك، لأن الالتزام الضريبي يوجب حفظها.'); ?>
                    </dd>
                </div>
            </dl>
            <p class="tq-caption" style="margin-block-start:var(--tq-space-l)">
                <?php echo t('إن أردت نسخة من بياناتك قبل الحذف، صدرها أولا —'); ?>
                <a href="<?php echo base_url('student/export_data'); ?>"><?php echo t('تصدير بياناتي'); ?></a>.
            </p>
        </section>

        <section class="tq-card">
            <div class="tq-row" style="flex-wrap:wrap">
                <a class="tq-btn tq-btn--secondary" href="<?php echo base_url('student/settings?s=security'); ?>"><?php echo t('تراجع'); ?></a>
                <form method="post" action="<?php echo base_url('student/delete_account'); ?>"
                      data-tq-confirm-title="<?php echo te('حذف حسابك نهائيا؟'); ?>"
                      data-tq-confirm="<?php echo te('تستبدل بياناتك بقيم مجهولة وتغلق جلستك في الحال. ولا يمكن استعادة الحساب بعدها.'); ?>"
                      data-tq-confirm-note="<?php echo te('تبقى فواتيرك بمعرف مجهول لأن حفظها التزام نظامي — ولا تحمل اسمك ولا بريدك.'); ?>"
                      data-tq-confirm-ok="<?php echo te('نعم، احذف حسابي'); ?>"
                      data-tq-confirm-tone="danger">
                    <input type="hidden" name="confirm" value="yes">
                    <button class="tq-btn tq-btn--danger" type="submit"><?php echo t('نعم، احذف حسابي'); ?></button>
                </form>
            </div>
        </section>
    </div>
</div>

<?php include 'portal_close.php'; ?>
