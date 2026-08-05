<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * تأكيد حذف الحساب.
 *
 * الحذف هنا **تجهيل لا محو**: تُستبدل بيانات الهوية بقيم مجهولة وتبقى
 * الفواتير بمعرّف مجهول لأن الالتزام الضريبي يوجب حفظها. ويُقال ذلك
 * للمستخدم صراحةً قبل أن يضغط — لا بعد.
 */
$tq_nav   = 'settings';
$tq_role  = 'student';
$tq_title = 'حذف الحساب';
$tq_sub   = 'إجراء لا رجعة فيه — اقرأ ما يحدث بالضبط';
$tq_icon  = 'lock';

include 'portal_open.php';
?>

<div class="tq-cols tq-cols--only" style="max-inline-size:720px">
    <div class="tq-stack">
        <section class="tq-card">
            <h2 class="tq-card__title">ماذا يحدث عند الحذف</h2>
            <dl class="tq-s-list">
                <div class="tq-s-row">
                    <dt class="tq-strong">يُجهَّل</dt>
                    <dd class="tq-caption" style="margin:0">اسمك وبريدك وجوّالك وعنوانك وصورتك ونبذتك — تُستبدل بقيم مجهولة.</dd>
                </div>
                <div class="tq-s-row">
                    <dt class="tq-strong">يُغلق</dt>
                    <dd class="tq-caption" style="margin:0">لن تستطيع الدخول بعدها، ولا استعادة الحساب.</dd>
                </div>
                <div class="tq-s-row">
                    <dt class="tq-strong">يبقى</dt>
                    <dd class="tq-caption" style="margin:0">
                        الفواتير والقيود المالية — بمعرّف مجهول لا يدلّ عليك، لأن الالتزام الضريبي يوجب حفظها.
                    </dd>
                </div>
            </dl>
            <p class="tq-caption" style="margin-block-start:var(--tq-space-l)">
                إن أردت نسخة من بياناتك قبل الحذف، صدّرها أولًا —
                <a href="<?php echo base_url('student/export_data'); ?>">تصدير بياناتي</a>.
            </p>
        </section>

        <section class="tq-card">
            <div class="tq-row" style="flex-wrap:wrap">
                <a class="tq-btn tq-btn--secondary" href="<?php echo base_url('student/settings?s=security'); ?>">تراجع</a>
                <form method="post" action="<?php echo base_url('student/delete_account'); ?>"
                      onsubmit="return confirm('سيُجهَّل حسابك ولا يمكن استعادته. هل أنت متأكّد؟')">
                    <input type="hidden" name="confirm" value="yes">
                    <button class="tq-btn tq-btn--danger" type="submit">نعم، احذف حسابي</button>
                </form>
            </div>
        </section>
    </div>
</div>

<?php include 'portal_close.php'; ?>
