<?php
/**
 * الأسئلة الشائعة — من `frontend_settings.website_faqs` (JSON).
 *
 * الطي يعمل بتفويض على `#faq` في `site.js`، فالمعرف والأصناف عقد لا زينة.
 */
$tq_h1    = 'الأسئلة الشائعة';
$tq_lead  = 'إجابات مختصرة عما يسأل كثيرا.';
$tq_shell = 'shell--read';
include __DIR__ . '/site/site_pagehero.php';

$tq_faqs = json_decode((string) get_frontend_settings('website_faqs'), true);
if (!is_array($tq_faqs)) $tq_faqs = array();
?>
<section class="section">
  <div class="shell shell--read">
    <?php if ($tq_faqs): ?>
      <div class="faq-grid" id="faq">
        <?php foreach ($tq_faqs as $tq_i => $tq_q): ?>
          <?php
          $tq_qq = is_array($tq_q) ? ($tq_q['question'] ?? $tq_q['title'] ?? '') : '';
          $tq_aa = is_array($tq_q) ? ($tq_q['answer'] ?? $tq_q['description'] ?? '') : '';
          if (trim($tq_qq) === '') continue;
          ?>
          <div class="faq-item">
            <button class="faq-q" type="button" aria-expanded="false"
                    aria-controls="faq-a-<?php echo (int) $tq_i; ?>"
                    id="faq-q-<?php echo (int) $tq_i; ?>">
              <span><?php echo html_escape($tq_qq); ?></span>
              <svg aria-hidden="true"><use href="#i-plus"></use></svg>
            </button>
            <?php /* الغلاف الداخلي عقد لا زينة: الطي `grid-template-rows:0fr`
                     على `.faq-a`، و`overflow:hidden` على ابنها المباشر —
                     `.faq-a>div` — هو ما يقص المحتوى فعلا. وبلا هذا الغلاف
                     يفيض النص من صف مقاسه صفر فيظهر كل جواب مفتوحا أبدا،
                     ولا تطوي الضغطة شيئا. انظر `contact_us.php`. */ ?>
            <div class="faq-a" id="faq-a-<?php echo (int) $tq_i; ?>" role="region"
                 aria-labelledby="faq-q-<?php echo (int) $tq_i; ?>"><div><p><?php echo html_escape($tq_aa); ?></p></div></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="icard">
        <p class="dir-empty">لم تضف أسئلة بعد.</p>
        <p class="tq-caption">وإن كان سؤالك عاجلا فـ<a href="<?php echo base_url('contact'); ?>">راسلنا</a> ونجيبك.</p>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php
/* TQ-SEO-FAQ — وسم الأسئلة الشائعة.

   الصفحة تحمل أسئلةً وأجوبةً حقيقية من `frontend_settings.website_faqs`،
   ولم يكن لها وسمٌ إطلاقًا. و`FAQPage` من الأنواع القليلة التي يعرضها
   جوجل تحت النتيجة نفسها — والشرط أن يكون السؤال والجواب **ظاهرَين
   للزائر**، وهما كذلك هنا (الطيّ يخفي الجواب بصريًّا لا من الصفحة). */
$tq_fld = array();
foreach ($tq_faqs as $tq_f) {
    $q = is_array($tq_f) ? trim(strip_tags((string) ($tq_f['question'] ?? $tq_f['title'] ?? ''))) : '';
    $a = is_array($tq_f) ? trim(strip_tags((string) ($tq_f['answer'] ?? $tq_f['description'] ?? ''))) : '';
    if ($q === '' || $a === '') continue;
    $tq_fld[] = array(
        '@type' => 'Question',
        'name'  => $q,
        'acceptedAnswer' => array('@type' => 'Answer', 'text' => $a),
    );
}
if ($tq_fld): ?>
<script type="application/ld+json"><?php
echo json_encode(array(
    '@context'   => 'https://schema.org',
    '@type'      => 'FAQPage',
    'inLanguage' => 'ar',
    'mainEntity' => $tq_fld,
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?></script>
<?php endif; ?>
