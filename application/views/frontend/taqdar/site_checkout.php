<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/**
 * تأكيد الاشتراك — الشاشة الوحيدة بين الرغبة والدفع.
 *
 * لا سلة: السلة تجمع أصنافا، والطالب يشتري باقة واحدة تغطي سنته.
 * فلا شيء يجمع، وكل خطوة زائدة هنا تسقط مشترين بلا مقابل.
 *
 * وما يعرض هنا **هو ما يكتب في القاعدة**: السعر من `plans.price` لا من
 * حقل مخفي في النموذج. أرسل المتصفح ما أرسل، المحرك يقرأ سعر الباقة
 * من عنده — فلا يشترى بستة ريالات ما ثمنه ستمئة.
 */
$b = isset($tq_bundle) ? $tq_bundle : null;
if (!$b) return;

$cur   = isset($tq_current) ? $tq_current : null;
$t     = $b['totals'];
$blocked = ($cur && $cur['status'] === 'active');
?>

<section class="page-hero page-hero--checkout">
  <?php include __DIR__ . '/site/site_arch.php'; ?>
  <div class="shell">
    <nav class="crumbs" aria-label="مسار التصفح">
      <a href="<?php echo base_url('plans'); ?>">الباقات</a> ›
      <a href="<?php echo base_url('plan/' . $b['code']); ?>"><?php echo html_escape(tqs_bundle_tier($b['name'])); ?></a> ›
      <span aria-current="page">تأكيد الاشتراك</span>
    </nav>
    <h1>تأكيد الاشتراك</h1>
    <p class="page-hero__lead">راجع ما ستشترك فيه، ثم أكد — ولن يخصم شيء تلقائيا.</p>
  </div>
</section>

<section class="section section--tight">
  <div class="shell co-cols">

    <div class="co-main">

      <?php if ($blocked): ?>
        <?php /* اشتراك نشط قائم: `subscribe()` ترفض بحق، ورسالتها تصل
                 بعد الضغط. وقولها هنا يمنع ضغطة تنتهي إلى خطأ. */ ?>
        <div class="icard co-blocked">
          <h2>لديك اشتراك نشط بالفعل</h2>
          <p>لا يمكن جمع اشتراكين في وقت واحد. أوقف تجديد اشتراكك الحالي
             أو انتظر انتهاء مدته، ثم عد إلى هنا.</p>
          <div class="co-blocked__acts">
            <a class="btn btn--primary" href="<?php echo base_url('student/subscription'); ?>">اشتراكي الحالي</a>
            <a class="btn btn--ghost" href="<?php echo base_url('student/bundle'); ?>">محتوى باقتي</a>
          </div>
        </div>
      <?php else: ?>

        <div class="icard co-summary">
          <h2>ملخص الطلب</h2>

          <div class="co-item">
            <?php if ((string) $b['image'] !== ''): ?>
              <img class="co-item__img" src="<?php echo tqs_asset_img($b['image'], 'path-primary'); ?>"
                   alt="" width="220" height="147" loading="lazy" decoding="async">
            <?php endif; ?>
            <div class="co-item__b">
              <h3><?php echo html_escape($b['name']); ?></h3>
              <?php if ($b['note'] !== ''): ?>
                <p class="tq-caption"><?php echo html_escape($b['note']); ?></p>
              <?php endif; ?>
              <p class="co-item__dur">
                <svg aria-hidden="true"><use href="#i-calendar"></use></svg>
                <?php echo html_escape(tqs_period_label($b['days'])); ?>
              </p>
            </div>
            <p class="co-item__price"><?php echo tqs_money($b['price']); ?></p>
          </div>

          <?php
          $facts = array();
          if ($t['grades']   > 0) $facts[] = $t['grades'] . ' صفوف دراسية';
          if ($t['subjects'] > 0) $facts[] = $t['subjects'] . ' مادة';
          if ($t['units']    > 0) $facts[] = $t['units'] . ' وحدة';
          if ($t['lessons']  > 0) $facts[] = $t['lessons'] . ' درسا';
          if ($t['quizzes']  > 0) $facts[] = $t['quizzes'] . ' اختبارا';
          if ($facts):
          ?>
            <p class="co-opens"><b>ما تفتحه:</b> <?php echo html_escape(implode(' · ', $facts)); ?>
              <a href="<?php echo base_url('plan/' . $b['code']); ?>#curriculum">شاهد المنهج كاملا</a>
            </p>
          <?php endif; ?>

          <dl class="co-total">
            <div><dt>سعر الباقة</dt><dd><?php echo tqs_money($b['price']); ?></dd></div>
            <?php /* لا ضريبة تضاف هنا: `issue_invoice()` تكتب `tax = 0`،
                     ورقم في العرض لا يقابله صف في الفاتورة يوقع في نزاع. */ ?>
            <div class="co-total__f"><dt>الإجمالي</dt><dd><?php echo tqs_money($b['price']); ?></dd></div>
          </dl>
        </div>

        <div class="icard">
          <h2>طريقة الدفع</h2>
          <p class="co-pay">
            <span class="co-pay__pick">
              <svg aria-hidden="true"><use href="#i-bank"></use></svg>
              <b>تحويل بنكي</b>
            </span>
            <span class="tq-caption">
              تصدر فاتورتك فورا برقم مرجعي، وتفعل باقتك بعد التحقق من الحوالة.
            </span>
          </p>
          <?php echo tqs_bank_block(); ?>
        </div>

      <?php endif; ?>
    </div>

    <?php if (!$blocked): ?>
    <aside class="co-side">
      <div class="icard icard--sticky">
        <h3 class="co-side__h">الخطوة الأخيرة</h3>
        <ol class="co-steps">
          <li><b>١</b> تؤكد الاشتراك وتصدر فاتورتك</li>
          <li><b>٢</b> تحول المبلغ بمرجع الفاتورة</li>
          <li><b>٣</b> يفعل اشتراكك ويفتح المحتوى</li>
        </ol>

        <p class="co-side__total">
          <span>الإجمالي</span>
          <b><?php echo tqs_money($b['price']); ?></b>
        </p>

        <?php
        /* `plan_id` وحده يرسل، والسعر يقرأ في المحرك من الباقة.
           و`REST_Output` يحقن حقل الحماية في كل نموذج `post` عند العرض. */
        ?>
        <form method="post" action="<?php echo base_url('student/subscribe'); ?>" class="co-form">
          <input type="hidden" name="plan_id" value="<?php echo (int) $b['plan_id']; ?>">
          <button type="submit" class="btn btn--primary btn--block">
            تأكيد الاشتراك وإصدار الفاتورة
          </button>
        </form>

        <p class="tq-caption co-side__note">
          لا تجديد تلقائي ولا خصم متكرر. وبتأكيدك توافق على
          <a href="<?php echo base_url('terms'); ?>">الشروط</a> و<a href="<?php echo base_url('refund'); ?>">سياسة الاسترداد</a>.
        </p>

        <a class="co-side__back" href="<?php echo base_url('plan/' . $b['code']); ?>">
          <svg aria-hidden="true"><use href="#i-arrow-back"></use></svg>
          عودة إلى تفاصيل الباقة
        </a>
      </div>
    </aside>
    <?php endif; ?>

  </div>
</section>
