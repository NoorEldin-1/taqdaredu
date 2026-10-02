<?php
/* ══════════════════════════════════════════════════════════════════
   TQ-HERO-LICENSE · «منشأة تدريب مرخصة» أعلى الهيرو + نافذة البيانات
   ══════════════════════════════════════════════════════════════════
   بقرار المالك (٢٠٢٦-١٠-٠٢)، واجهة وحدها: البيانات ثابتة هنا لا من
   قاعدة ولا من خدمة. والموضع الوحيد لتعديلها هو المصفوفة التالية.

   ويُضمَّن الملف مرتين من `home.php`:
     $tq_lic_part = 'card'   ⇐ البطاقة، **داخل** `.hero__frame`.
     $tq_lic_part = 'dialog' ⇐ النافذة وسكربتها، **خارج** الإطار.
   والفصل ضرورة لا ترتيب: الورقة تعطي كل ابن لـ`.hero__frame`
   `position:relative`، و`<dialog>` المفتوح بـ`showModal()` يعيش في
   الطبقة العليا بـ`position:fixed` من المتصفح — فقاعدة الإطار تسحبه
   إلى مجرى الصفحة فلا يظهر نافذة وسط الشاشة.

   والشعار ملف المالك كما أرسله (٣٦٣×١٣٩، خلفية بيضاء) بلا تعديل في
   ألوانه ولا نسبه — والبطاقة بيضاء فتبتلع الخلفية بلا رقعة.
   ══════════════════════════════════════════════════════════════════ */

$tq_lic = array(
    'status'  => 'منشأة تدريب مرخصة',
    'issuer'  => 'المؤسسة العامة للتدريب التقني والمهني',
    'license' => '19187335027700',
    'unified' => '7054960104',
    'logo'    => 'img/nelc-logo.png',
    'logo_w'  => 363,
    'logo_h'  => 139,
    'logo_alt'=> 'المركز الوطني للتعليم الإلكتروني — National eLearning Center',
);
$tq_lic_part = isset($tq_lic_part) ? $tq_lic_part : 'card';

/* ختم التحقق الأخضر: ستة عشر نتوءا حول دائرة وعلامة صح بيضاء. */
$tq_lic_seal = '<svg class="tqlic__seal" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
    . '<path fill="#1F9D55" d="M12 0.4L13.99 2L16.44 1.28L17.67 3.52L20.2 3.8L20.48 6.33L22.72 7.56L22 10.01L23.6 12'
    . 'L22 13.99L22.72 16.44L20.48 17.67L20.2 20.2L17.67 20.48L16.44 22.72L13.99 22L12 23.6L10.01 22L7.56 22.72'
    . 'L6.33 20.48L3.8 20.2L3.52 17.67L1.28 16.44L2 13.99L0.4 12L2 10.01L1.28 7.56L3.52 6.33L3.8 3.8L6.33 3.52'
    . 'L7.56 1.28L10.01 2Z"/>'
    . '<path fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M7.4 12.3l3.1 3.1 6.1-6.3"/>'
    . '</svg>';

if ($tq_lic_part === 'card'): ?>
      <aside class="tqlic reveal" aria-label="ترخيص المنشأة">
        <img class="tqlic__logo" src="<?php echo tq_site_asset($tq_lic['logo']); ?>"
             width="<?php echo $tq_lic['logo_w']; ?>" height="<?php echo $tq_lic['logo_h']; ?>"
             alt="<?php echo html_escape($tq_lic['logo_alt']); ?>" decoding="async">
        <span class="tqlic__rule" aria-hidden="true"></span>
        <div class="tqlic__body">
          <p class="tqlic__title"><?php echo $tq_lic_seal; ?><span><?php echo html_escape($tq_lic['status']); ?></span></p>
          <p class="tqlic__issuer">مرخصة من <?php echo html_escape($tq_lic['issuer']); ?></p>
          <dl class="tqlic__meta">
            <div><dt>رقم الترخيص:</dt> <dd class="tq-ltr"><?php echo html_escape($tq_lic['license']); ?></dd></div>
            <div><dt>الرقم الموحد:</dt> <dd class="tq-ltr"><?php echo html_escape($tq_lic['unified']); ?></dd></div>
          </dl>
          <button class="tqlic__more" type="button" data-tqlic-open
                  aria-haspopup="dialog" aria-controls="tqlic-dialog">
            عرض بيانات الترخيص
            <svg class="dir-icon" aria-hidden="true"><use href="#i-arrow"></use></svg>
          </button>
        </div>
      </aside>
<?php elseif ($tq_lic_part === 'dialog'): ?>
<dialog class="tqlic-dlg" id="tqlic-dialog" aria-labelledby="tqlic-dlg-title">
  <?php /* الغلاف الداخلي يملأ النافذة فينقر خارجه على الخلفية وحدها —
           هو ما يجعل النقر على الخلفية يغلقها بلا حساب إحداثيات. */ ?>
  <div class="tqlic-dlg__in">
    <button class="tqlic-dlg__x" type="button" data-tqlic-close aria-label="إغلاق">
      <svg aria-hidden="true"><use href="#i-close"></use></svg>
    </button>
    <div class="tqlic-dlg__head">
      <img class="tqlic-dlg__logo" src="<?php echo tq_site_asset($tq_lic['logo']); ?>"
           width="<?php echo $tq_lic['logo_w']; ?>" height="<?php echo $tq_lic['logo_h']; ?>"
           alt="<?php echo html_escape($tq_lic['logo_alt']); ?>" loading="lazy" decoding="async">
      <div>
        <h2 id="tqlic-dlg-title">بيانات الترخيص</h2>
        <p class="tqlic__title"><?php echo $tq_lic_seal; ?><span><?php echo html_escape($tq_lic['status']); ?></span></p>
      </div>
    </div>
    <dl class="tqlic-dlg__rows">
      <div><dt>حالة المنشأة</dt><dd><?php echo html_escape($tq_lic['status']); ?></dd></div>
      <div><dt>الجهة المرخصة</dt><dd><?php echo html_escape($tq_lic['issuer']); ?></dd></div>
      <div><dt>رقم الترخيص</dt><dd class="tq-ltr tqlic-dlg__num"><?php echo html_escape($tq_lic['license']); ?></dd></div>
      <div><dt>الرقم الموحد</dt><dd class="tq-ltr tqlic-dlg__num"><?php echo html_escape($tq_lic['unified']); ?></dd></div>
    </dl>
    <div class="tqlic-dlg__foot">
      <button class="btn btn--primary" type="button" data-tqlic-close>إغلاق</button>
    </div>
  </div>
</dialog>
<script>
/* TQ-HERO-LICENSE — فتح النافذة وإغلاقها. Esc وإرجاع التركيز إلى الزر
   يتولاهما `<dialog>` نفسه؛ وهنا النقر على الخلفية وزرّا الإغلاق.
   ومتصفح بلا `showModal` يبقي الزر مخفيا: الأرقام في البطاقة نفسها. */
(function () {
  var d = document.getElementById('tqlic-dialog'),
      o = document.querySelector('[data-tqlic-open]');
  if (!d || !o) return;
  if (typeof d.showModal !== 'function') { o.hidden = true; return; }
  o.addEventListener('click', function () { d.showModal(); });
  d.addEventListener('click', function (e) {
    if (e.target === d || (e.target.closest && e.target.closest('[data-tqlic-close]'))) d.close();
  });
})();
</script>
<?php endif; ?>
