<?php
/* ══════════════════════════════════════════════════════════════════
   TQ-WA-GROUP · مجموعة دعم العملاء عبر واتساب
   ══════════════════════════════════════════════════════════════════
   بطاقة واحدة تطبع في الرئيسية وفي «تواصل معنا» — بقرار المالك
   (٢٠٢٦-٠٩-٢٢). والرابط في موضع واحد: الثابت `TQ_WA_SUPPORT_GROUP`
   في `application/config/constants.php`. فرغه تختف البطاقة من
   الصفحتين معا، فلا زر يقود إلى لا شيء.

   وهي غير زر واتساب العائم (`site_whatsapp.php`): ذاك محادثة فردية
   برقم الدعم من الإعدادات، وهذه مجموعة أسئلة وأجوبة عامة.

   المدخلات (اختيارية، تضبط قبل `include`):
     $tq_wa_section  true ⇐ تلف البطاقة في `<section>` بعرض الصفحة
                     (الرئيسية). وإلا تطبع البطاقة وحدها داخل لوح قائم.
     $tq_wa_h        وسم العنوان: `h2` قسما مستقلا، `h3` داخل لوح له
                     عنوانه (تواصل معنا) — فلا يقفز مخطط العناوين.
   ══════════════════════════════════════════════════════════════════ */

$tq_wa_url = tqs_wa_group_href();   /* الفحص في الدالّة — موضع واحد */
if ($tq_wa_url === '') return;
$tq_wa_h = (isset($tq_wa_h) && in_array($tq_wa_h, array('h2', 'h3'), true)) ? $tq_wa_h : 'h2';
$tq_wa_section = !empty($tq_wa_section);
?>
<?php if ($tq_wa_section): ?>
<section class="section" id="support" aria-labelledby="tqwa-title">
  <div class="shell">
<?php endif; ?>
    <div class="tqwa reveal"<?php echo $tq_wa_section ? '' : ' role="region" aria-labelledby="tqwa-title"'; ?>>
      <span class="tqwa__ico" aria-hidden="true"><svg><use href="#i-whatsapp"></use></svg></span>
      <div class="tqwa__copy">
        <<?php echo $tq_wa_h; ?> class="tqwa__title" id="tqwa-title">مجموعة دعم العملاء عبر واتساب</<?php echo $tq_wa_h; ?>>
        <p class="tqwa__lede">اسأل فريق تقدر عن التسجيل والدفع واستخدام المنصة، واحصل على الرد داخل مجموعة الدعم.</p>
      </div>
      <a class="btn btn--gold tqwa__cta" href="<?php echo html_escape($tq_wa_url); ?>"
         target="_blank" rel="noopener noreferrer"
         aria-label="انضم إلى مجموعة دعم العملاء عبر واتساب — يفتح في نافذة جديدة">
        <svg aria-hidden="true"><use href="#i-whatsapp"></use></svg>
        انضم إلى مجموعة الدعم
      </a>
    </div>
<?php if ($tq_wa_section): ?>
  </div>
</section>
<?php endif; ?>
