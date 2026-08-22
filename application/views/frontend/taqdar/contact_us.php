<!--
title: تواصل معنا — منصة تقدر
desc: فريق تقدر جاهز للإجابة على استفساراتك وتقديم الدعم والمساعدة في رحلتك التعليمية.
active: contact
header: dark
css: pages
-->

<!-- ══════════ الهيرو ══════════ -->
<section class="page-hero">
  <div class="shell">
    <div class="page-hero__grid">
      <span class="lantern lantern--l" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>

      <div class="page-hero__copy reveal">
        <h1><?php echo tq_text('contact_us', 'hero_title', 'تواصل معنا'); ?>
          <span class="page-hero__sub"><?php
            echo tq_text('contact_us', 'hero_sub', 'نحن هنا لمساعدتك'); ?></span>
        </h1>
        <p class="page-hero__lede">
          <?php echo tq_text('contact_us', 'hero_lede',
              'فريق تقدر جاهز للإجابة على استفساراتك وتقديم الدعم والمساعدة في رحلتك التعليمية. لا تتردد في التواصل معنا في أي وقت.'); ?>
        </p>
        <div class="hero-mini">
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-users"></use></svg></span>
            <div><b>نحن نهتم بك</b><span>رضاك هو أولويتنا</span></div>
          </div>
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-shield"></use></svg></span>
            <div><b>خصوصيتك آمنة</b><span>نحافظ على سرية معلوماتك</span></div>
          </div>
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-support"></use></svg></span>
            <div><b>دعم متخصص</b><span>فريق ذو خبرة لمساعدتك</span></div>
          </div>
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-clock"></use></svg></span>
            <div><b>استجابة سريعة</b><span>نرد في أسرع وقت ممكن</span></div>
          </div>
        </div>
      </div>

      <div class="page-hero__art reveal">
        <div class="page-hero__arch">
          <div>
            <img src="<?php echo tq_site_asset('img/contact-hero.webp'); ?>" width="960" height="1440"
                 alt="طفلان سعوديان يتشاركان حاسوبا محمولا">
          </div>
<?php include __DIR__ . '/site/site_arch.php'; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══════════ طرق التواصل + النموذج ══════════ -->
<section class="section">
  <div class="shell">
    <div class="panel">
      <div class="section-head">
        <h2><span>اختر طريقة التواصل الأنسب لك</span></h2>
        <div class="rule"><svg aria-hidden="true"><use href="#i-star8"></use></svg></div>
      </div>

      <div class="contact-split">
        <div>
          <a class="contact-card reveal" href="<?php echo tqs_tel_href(); ?>">
            <span class="ico"><svg aria-hidden="true"><use href="#i-phone"></use></svg></span>
            <div>
              <h3>اتصل بنا</h3>
              <p>تحدث مباشرة مع فريق الدعم</p>
              <b class="tq-ltr"><?php echo tqs_phone_text(); ?></b>
              <p>من الأحد إلى الخميس · <span class="tq-ltr">9:00</span> ص –
                 <span class="tq-ltr">5:00</span> م</p>
            </div>
          </a>
          <a class="contact-card reveal" href="mailto:<?php echo html_escape(get_settings('system_email')); ?>">
            <span class="ico"><svg aria-hidden="true"><use href="#i-mail"></use></svg></span>
            <div>
              <h3>راسلنا على البريد</h3>
              <p>أرسل لنا استفسارك وسنرد عليك</p>
              <b><?php echo html_escape(get_settings('system_email')); ?></b>
              <p>نجيب على رسائلك خلال <span class="tq-ltr">24</span> ساعة</p>
            </div>
          </a>
          <a class="contact-card reveal" href="<?php echo tqs_whatsapp_href(); ?>" id="whatsapp">
            <span class="ico"><svg aria-hidden="true"><use href="#i-whatsapp"></use></svg></span>
            <div>
              <h3>تواصل عبر واتساب</h3>
              <p>راسلنا على واتساب للحصول على دعم فوري</p>
              <b class="tq-ltr"><?php echo tqs_whatsapp_text(); ?></b>
              <p>متاح من <span class="tq-ltr">9:00</span> ص –
                 <span class="tq-ltr">10:00</span> م</p>
            </div>
          </a>
          <a class="contact-card reveal" href="#visit">
            <span class="ico"><svg aria-hidden="true"><use href="#i-pin"></use></svg></span>
            <div>
              <h3>موقعنا</h3>
              <p>الرياض — المملكة العربية السعودية<br>
                 حي العليا · طريق الملك فهد<br>
                 برج النهضة — الدور <span class="tq-ltr">12</span></p>
            </div>
          </a>
        </div>

        <div class="form-card reveal" id="contact-form">
          <h2>أرسل لنا رسالة</h2>
          <p>املأ النموذج وسنعود إليك في أقرب وقت</p>
          <?php /* `tq_csrf()` صراحة: `includes_bottom.php` يحقن الرمز
                   بجافاسكربت عند الإرسال، ونموذج يعتمد على ملف JS ليحفظ
                   يسقط صامتا متى تعثر الملف. */ ?>
          <form method="post" action="<?php echo base_url('home/contact_us/submit'); ?>" data-validate novalidate>
            <?php echo tq_csrf(); ?>
            <div class="form-grid">
              <label class="form-field">
                <svg aria-hidden="true"><use href="#i-user"></use></svg>
                <span class="sr-only">الاسم الكامل</span>
                <input type="text" name="first_name" id="cName" required
                       autocomplete="name" placeholder="الاسم الكامل">
              </label>
              <label class="form-field">
                <svg aria-hidden="true"><use href="#i-mail"></use></svg>
                <span class="sr-only">البريد الإلكتروني</span>
                <input type="email" name="email" id="cMail" required
                       autocomplete="email" placeholder="البريد الإلكتروني">
              </label>
              <label class="form-field">
                <svg aria-hidden="true"><use href="#i-phone"></use></svg>
                <span class="sr-only">رقم الجوال</span>
                <span class="form-prefix tq-ltr">+966</span>
                <input type="tel" name="phone" id="cTel" required inputmode="numeric"
                       autocomplete="tel-national"
                       pattern="5[0-9]{8}" placeholder="5XXXXXXXX">
              </label>
              <label class="form-field">
                <svg aria-hidden="true"><use href="#i-list"></use></svg>
                <span class="sr-only">الموضوع</span>
                <select name="subject" id="cSubj" required>
                  <?php /* `disabled` لا `value=""` وحدها: بدونها يمر النموذج بموضوع
        فارغ، فتصل رسالة لا يعرف إلى أي فريق توجه. */ ?>
                  <option value="" disabled selected>اختر الموضوع…</option>
                  <option>استفسار عن البرامج</option>
                  <option>الدعم الفني</option>
                  <option>الاشتراكات والفواتير</option>
                  <option>الانضمام كمعلم</option>
                  <option>أخرى</option>
                </select>
              </label>
              <label class="form-field form-field--full">
                <svg aria-hidden="true"><use href="#i-pen"></use></svg>
                <span class="sr-only">رسالتك</span>
                <textarea name="message" id="cMsg" required
                          placeholder="اكتب استفسارك أو رسالتك هنا…"></textarea>
              </label>
            </div>
            <input type="hidden" name="last_name" value="">
            <input type="hidden" name="address" value="">
            <label class="form-consent form-field--full">
              <input type="checkbox" name="i_agree" value="1" required>
              <span>أوافق على معالجة بياناتي للرد على رسالتي.</span>
            </label>
            <button class="btn btn--primary" type="submit">
              إرسال الرسالة
              <svg aria-hidden="true" style="width:17px;height:17px">
                <use href="#i-send"></use></svg>
            </button>
          </form>
          <?php if ($tq_f = $this->session->flashdata('flash_message')): ?><p class="form-ok is-on" role="status"><?php echo html_escape($tq_f); ?></p><?php endif; ?><?php if ($tq_e = $this->session->flashdata('error_message')): ?><p class="tq-flash tq-flash--err" role="alert"><?php echo html_escape($tq_e); ?></p><?php endif; ?><p class="form-ok" data-ok role="status">
            تم استلام رسالتك — سنعود إليك خلال <span class="tq-ltr">24</span> ساعة.
          </p>
          
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══════════ زورنا في مقرنا ══════════ -->
<section class="section section--plain" id="visit">
  <div class="shell">
    <div class="visit reveal">
      <?php /* خريطة حقيقية من OpenStreetMap — لا مفتاح ولا كوكيز ولا موافقة.
              وخرائط جوجل تضع كوكيز طرف ثالث قبل أن يقبل الزائر شريط
              الموافقة، فتخالف ما يعد به الشريط. والزر تحتها يفتح جوجل
              عند الطلب — وذلك اختيار الزائر لا اختيارنا عنه.
              و`loading=lazy` كي لا تحمل خريطة في أسفل الصفحة مع أولها. */ ?>
      <div class="visit__map">
        <iframe title="خريطة موقع مقر منصة تقدر — حي العليا، الرياض"
                src="https://www.openstreetmap.org/export/embed.html?bbox=46.6650%2C24.6820%2C46.6980%2C24.7060&amp;layer=mapnik&amp;marker=24.6940%2C46.6815"
                loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                style="border:0"></iframe>
      </div>

      <div class="visit__panel">
        <h2>زورنا في مقرنا</h2>
        <p>نرحب بزيارتك في مقر منصة تقدر</p>
        <?php /* لكل بند أيقونة تخصه: كان «الوصول سهل» يحمل دبوس الموقع
                 نفسه الذي تحمله بطاقة «موقعنا» فوقه، و«مواقف مجانية»
                 تحمل علما لا علاقة له. والسطران في كل بند متقابلان طولا
                 فلا يبقى سطر يتيم بكلمة واحدة. */ ?>
        <div class="visit__items">
          <div>
            <span class="ico"><svg aria-hidden="true"><use href="#i-clock"></use></svg></span>
            <b>أوقات العمل</b>
            <span>الأحد – الخميس<br><span class="tq-ltr">9:00</span> ص –
              <span class="tq-ltr">5:00</span> م</span>
          </div>
          <div>
            <span class="ico"><svg aria-hidden="true"><use href="#i-route"></use></svg></span>
            <b>الوصول سهل</b>
            <span>قريب من محطة المترو<br>ومركز الملك عبدالله المالي</span>
          </div>
          <div>
            <span class="ico"><svg aria-hidden="true"><use href="#i-car"></use></svg></span>
            <b>مواقف مجانية</b>
            <span>مواقف سيارات للزوار<br>داخل البرج وبجواره</span>
          </div>
        </div>
        <?php /* كان يشير إلى `#visit` — أي إلى نفسه، فالضغط لا يفعل شيئا. */ ?>
        <a class="btn btn--gold" target="_blank" rel="noopener noreferrer"
           href="https://www.google.com/maps/search/?api=1&amp;query=%D8%AD%D9%8A+%D8%A7%D9%84%D8%B9%D9%84%D9%8A%D8%A7%D8%8C+%D8%A7%D9%84%D8%B1%D9%8A%D8%A7%D8%B6">
          احصل على الاتجاهات
          <svg aria-hidden="true" style="width:16px;height:16px"><use href="#i-send"></use></svg>
        </a>
      </div>

      <div class="visit__photo">
        <img src="<?php echo tq_site_asset('img/office.webp'); ?>" width="880" height="587" loading="lazy"
             decoding="async" alt="مدخل مقر منصة تقدر بجدار أخضر وزخرفة عربية">
      </div>
    </div>
  </div>
</section>

<!-- ══════════ الأسئلة الشائعة ══════════ -->
<?php
/**
 * TQ-FAQ-ONE · مصدر واحد للأسئلة الشائعة.
 *
 * كانت ستة أسئلة **مكتوبة في هذا القالب بيدها**، بينما صفحة `/faq`
 * تقرأ من `frontend_settings.website_faqs` التي تحرر في اللوحة:
 * «الموقع ← إعدادات الواجهة ← الأسئلة الشائعة». فمن عدل السؤال في
 * اللوحة رآه يتغير في صفحة ويبقى كما كان في أخرى، ولا شيء يقول له
 * لماذا. والمصدر هنا هو المصدر نفسه.
 *
 * ولا تعرض الصفحة كل ما في اللوحة: هذا ملحق في صفحة تواصل لا صفحة
 * أسئلة، فستة منها وبعدها رابط إلى الصفحة الكاملة.
 */
$tq_faqs = json_decode((string) get_frontend_settings('website_faqs'), true);
if (!is_array($tq_faqs)) $tq_faqs = array();

$tq_faqs = array_values(array_filter($tq_faqs, function ($f) {
    return is_array($f) && trim((string) ($f['question'] ?? '')) !== '';
}));
$tq_faq_all   = count($tq_faqs);
$tq_faq_shown = array_slice($tq_faqs, 0, 6);
?>
<?php if ($tq_faq_shown): ?>
<section class="section section--plain" id="faq">
  <div class="shell">
    <div class="section-head">
      <h2><span>الأسئلة الشائعة</span></h2>
      <div class="rule"><svg aria-hidden="true"><use href="#i-star8"></use></svg></div>
    </div>

    <div class="faq-layout">
      <?php /* المعرف كان `faq` على القسم وعلى الشبكة معا — معرفان
               متكرران في صفحة واحدة، و`site.js` يفوض الطي على `#faq`
               فيمسك بالأول (القسم) لا بالشبكة. والشبكة هنا `#faqGrid`،
               والقسم يبقى مرساة الرابط. */ ?>
      <div class="faq-grid" id="faqGrid">
        <?php foreach ($tq_faq_shown as $tq_i => $tq_f): ?>
          <div class="faq-item reveal">
            <button class="faq-q" type="button" aria-expanded="false"
                    aria-controls="cfaq-a-<?php echo (int) $tq_i; ?>"
                    id="cfaq-q-<?php echo (int) $tq_i; ?>">
              <span><?php echo html_escape($tq_f['question']); ?></span>
              <svg aria-hidden="true"><use href="#i-plus"></use></svg>
            </button>
            <?php /* الغلاف الداخلي عقد لا زينة — انظر `website_faq.php`:
                     الطي `grid-template-rows:0fr` على `.faq-a`، والقص
                     على ابنها المباشر. وبلاه يظهر كل جواب مفتوحا أبدا. */ ?>
            <div class="faq-a" id="cfaq-a-<?php echo (int) $tq_i; ?>" role="region"
                 aria-labelledby="cfaq-q-<?php echo (int) $tq_i; ?>"><div><p><?php
              /* الأجوبة تحرر في اللوحة وقد تحمل روابط — و`html_escape`
                 كانت تطبعها وسما ظاهرا. فقائمة بيضاء ضيقة تبقي الرابط
                 والتوكيد وتسقط ما سواهما. */
              echo strip_tags((string) ($tq_f['answer'] ?? ''), '<a><strong><b><em><i><br>');
            ?></p></div></div>
          </div>
        <?php endforeach; ?>

        <?php if ($tq_faq_all > count($tq_faq_shown)): ?>
          <p class="faq-more">
            <a class="btn btn--ghost" href="<?php echo base_url('faq'); ?>">
              كل الأسئلة الشائعة
              <span class="tq-ltr">(<?php echo (int) $tq_faq_all; ?>)</span>
              <svg aria-hidden="true" style="width:16px;height:16px"><use href="#i-arrow"></use></svg>
            </a>
          </p>
        <?php endif; ?>
      </div>

      <div class="help-card reveal">
        <span class="ico"><svg aria-hidden="true"><use href="#i-chat"></use></svg></span>
        <h3>لم تجد إجابة لسؤالك؟</h3>
        <p>فريق الدعم لدينا مستعد لمساعدتك في أي وقت</p>
        <?php /* كان `#top` ولا عنصر بهذا المعرف في الصفحة — رابط لا يفعل
        شيئا. ووجهته الصحيحة نموذج المراسلة في الصفحة نفسها. */ ?>
        <a class="btn btn--primary" href="#contact-form">
          <svg aria-hidden="true" style="width:16px;height:16px"><use href="#i-support"></use></svg>
          تواصل معنا الآن
        </a>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>
