<?php
/**
 * الشبكة وما فوقها وما تحتها — الجزء الذي يستبدل مع كل بحث.
 *
 * ثلاثة أشياء لا شيء واحد: رقائق ما هو مفعل، والبطاقات، والترقيم.
 * وجمعها في جزء واحد مقصود — الثلاثة تشتق من نتيجة واحدة، وتحديث
 * أحدها دون أخويه هو نفسه التعارض الذي بني هذا كله لتفاديه: ترقيم
 * يقول «صفحة ٣ من ٥» فوق شبكة فيها نتيجة واحدة.
 *
 * وصندوق البحث **خارج** هذا الجزء عمدا: استبداله مع كل حرف يفقد
 * المؤشر موضعه، فيكتب الزائر حرفا ثم يجد المؤشر عاد إلى أول السطر.
 */
?>
<?php
/* مرشح حقن نفسه يقول ذلك.
   الطالب يفتح الكتالوج فيجد مرحلته مرشحة سلفا — وترشيح بلا سطر يفسره
   يقرأ نقصا في المحتوى لا تقريبا منه: «أين بقية المواد؟». والسطر يقول
   من رشح ولماذا، ويعطي الباب الذي يخرج منه في نقرة. */
if (!empty($tq_f['auto'])): ?>
<p class="cscope">
  <svg aria-hidden="true"><use href="#i-cap"></use></svg>
  <span>عرضنا لك <b><?php echo html_escape($tq_f['mine_label']); ?></b> لأنها مرحلة صفك،
        ورشح بالصف من اللوحة لتصل إلى ما يخصك وحدك.</span>
  <a href="<?php echo html_escape(tqs_cat_query($tq_f, array('cat' => null))); ?>" data-tq-cat-link>
    اعرض كل المراحل
  </a>
</p>
<?php endif; ?>

<?php if ($tq_res['active']): ?>
<div class="cactive" aria-label="المرشحات المفعلة">
<?php foreach ($tq_res['active'] as $tq_c): ?>
  <a class="cactive__i"
     href="<?php echo html_escape(($tq_c['key'] === 'q' || $tq_c['key'] === 'price')
         ? tqs_cat_query($tq_f, array($tq_c['key'] => null))
         : tqs_cat_toggle($tq_f, $tq_c['key'], $tq_c['value'])); ?>"
     data-tq-cat-link>
    <?php echo html_escape($tq_c['label']); ?>
    <svg aria-hidden="true"><use href="#i-close"></use></svg>
    <span class="sr-only">احذف هذا المرشح</span>
  </a>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($tq_res['items']): ?>
<div class="cgrid">
<?php foreach ($tq_res['items'] as $tq_it) echo tqs_cat_card($tq_it); ?>
</div>

<?php echo tqs_cat_pager($tq_f, $tq_res['page'], $tq_res['pages']); ?>

<?php else: ?>
<?php /* الفراغ يقول سببه ويعطي مخرجا: «لا نتائج» وحدها تترك الزائر
        أمام شاشة بيضاء لا يعرف أخطأ في الكتابة أم ضيق المرشحات. */ ?>
<div class="cempty">
  <span class="cempty__ico" aria-hidden="true"><svg><use href="#i-search"></use></svg></span>
  <h3>لا نتائج تطابق ما اخترت</h3>
<?php if ($tq_f['q'] !== ''): ?>
  <p>لم نجد شيئا باسم «<?php echo html_escape($tq_f['q']); ?>». جرب كلمة أقصر، أو وسع المرشحات.</p>
<?php else: ?>
  <p>المرشحات المختارة معا لا يجتمع فيها شيء. احذف واحدا منها وجرب.</p>
<?php endif; ?>
  <?php /* `tqs_cat_clear` لا `base_url('catalog')`: الرابط العاري يعيد
           حقن مرحلة الطالب، فيقرأ الزر «اعرض كل المحتوى» ويعرض مرحلة. */ ?>
  <a class="btn btn--primary" href="<?php echo html_escape(tqs_cat_clear($tq_f)); ?>" data-tq-cat-link>
    اعرض كل المحتوى
  </a>
</div>
<?php endif; ?>
