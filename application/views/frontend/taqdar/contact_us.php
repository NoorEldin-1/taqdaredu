<!--
title: تواصل معنا — منصّة تقدّر
desc: فريق تقدّر جاهز للإجابة على استفساراتك وتقديم الدعم والمساعدة في رحلتك التعليمية.
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
        <h1>تواصل معنا
          <span class="page-hero__sub">نحن هنا لمساعدتك</span>
        </h1>
        <p class="page-hero__lede">
          فريق تقدّر جاهز للإجابة على استفساراتك وتقديم الدعم والمساعدة
          في رحلتك التعليمية. لا تتردّد في التواصل معنا في أي وقت.
        </p>
        <div class="hero-mini">
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-users"></use></svg></span>
            <div><b>نحن نهتم بك</b><span>رضاك هو أولويتنا</span></div>
          </div>
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-shield"></use></svg></span>
            <div><b>خصوصيتك آمنة</b><span>نحافظ على سرّية معلوماتك</span></div>
          </div>
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-support"></use></svg></span>
            <div><b>دعم متخصّص</b><span>فريق ذو خبرة لمساعدتك</span></div>
          </div>
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-clock"></use></svg></span>
            <div><b>استجابة سريعة</b><span>نردّ في أسرع وقت ممكن</span></div>
          </div>
        </div>
      </div>

      <div class="page-hero__art reveal">
        <div class="page-hero__arch">
          <div>
            <img src="<?php echo tq_site_asset('img/contact-hero.webp'); ?>" width="960" height="1440"
                 alt="طفلان سعوديان يتشاركان حاسوبًا محمولًا">
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
              <p>تحدّث مباشرة مع فريق الدعم</p>
              <b class="tq-ltr"><?php echo tqs_phone_text(); ?></b>
              <p>من الأحد إلى الخميس · <span class="tq-ltr">9:00</span> ص –
                 <span class="tq-ltr">5:00</span> م</p>
            </div>
          </a>
          <a class="contact-card reveal" href="mailto:<?php echo html_escape(get_settings('system_email')); ?>">
            <span class="ico"><svg aria-hidden="true"><use href="#i-mail"></use></svg></span>
            <div>
              <h3>راسلنا على البريد</h3>
              <p>أرسل لنا استفسارك وسنردّ عليك</p>
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
          <form method="post" action="<?php echo base_url('home/contact_us/submit'); ?>" data-validate novalidate>
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
                  <?php /* `disabled` لا `value=""` وحدها: بدونها يمرّ النموذج بموضوعٍ
        فارغ، فتصل رسالةٌ لا يُعرف إلى أيّ فريقٍ توجَّه. */ ?>
                  <option value="" disabled selected>اختر الموضوع…</option>
                  <option>استفسار عن البرامج</option>
                  <option>الدعم الفني</option>
                  <option>الاشتراكات والفواتير</option>
                  <option>الانضمام كمعلّم</option>
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
              <span>أوافق على معالجة بياناتي للردّ على رسالتي.</span>
            </label>
            <button class="btn btn--primary" type="submit">
              إرسال الرسالة
              <svg aria-hidden="true" style="width:17px;height:17px">
                <use href="#i-send"></use></svg>
            </button>
          </form>
          <?php if ($tq_f = $this->session->flashdata('flash_message')): ?><p class="form-ok is-on" role="status"><?php echo html_escape($tq_f); ?></p><?php endif; ?><?php if ($tq_e = $this->session->flashdata('error_message')): ?><p class="tq-flash tq-flash--err" role="alert"><?php echo html_escape($tq_e); ?></p><?php endif; ?><p class="form-ok" data-ok role="status">
            تمّ استلام رسالتك — سنعود إليك خلال <span class="tq-ltr">24</span> ساعة.
          </p>
          
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══════════ زورنا في مقرّنا ══════════ -->
<section class="section section--plain" id="visit">
  <div class="shell">
    <div class="visit reveal">
      <?php /* خريطة حقيقية من OpenStreetMap — لا مفتاح ولا كوكيز ولا موافقة.
              وخرائط جوجل تضع كوكيز طرفٍ ثالث قبل أن يقبل الزائر شريط
              الموافقة، فتُخالف ما يَعِد به الشريط. والزرّ تحتها يفتح جوجل
              عند الطلب — وذلك اختيارُ الزائر لا اختيارُنا عنه.
              و`loading=lazy` كي لا تُحمَّل خريطةٌ في أسفل الصفحة مع أوّلها. */ ?>
      <div class="visit__map">
        <iframe title="خريطة موقع مقرّ منصة تقدّر — حيّ العليا، الرياض"
                src="https://www.openstreetmap.org/export/embed.html?bbox=46.6650%2C24.6820%2C46.6980%2C24.7060&amp;layer=mapnik&amp;marker=24.6940%2C46.6815"
                loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                style="border:0"></iframe>
      </div>

      <div class="visit__panel">
        <h2>زورنا في مقرّنا</h2>
        <p>نرحّب بزيارتك في مقرّ منصة تقدّر</p>
        <div class="visit__items">
          <div>
            <span class="ico"><svg aria-hidden="true"><use href="#i-clock"></use></svg></span>
            <b>أوقات العمل</b>
            <span>الأحد – الخميس<br><span class="tq-ltr">9:00</span> ص –
              <span class="tq-ltr">5:00</span> م</span>
          </div>
          <div>
            <span class="ico"><svg aria-hidden="true"><use href="#i-pin"></use></svg></span>
            <b>الوصول سهل</b>
            <span>قريب من محطة مترو<br>مركز الملك عبدالله المالي</span>
          </div>
          <div>
            <span class="ico"><svg aria-hidden="true"><use href="#i-flag"></use></svg></span>
            <b>مواقف مجانية</b>
            <span>تتوفّر مواقف سيارات<br>للزوّار</span>
          </div>
        </div>
        <?php /* كان يشير إلى `#visit` — أي إلى نفسه، فالضغط لا يفعل شيئًا. */ ?>
        <a class="btn btn--gold" target="_blank" rel="noopener noreferrer"
           href="https://www.google.com/maps/search/?api=1&amp;query=%D8%AD%D9%8A+%D8%A7%D9%84%D8%B9%D9%84%D9%8A%D8%A7%D8%8C+%D8%A7%D9%84%D8%B1%D9%8A%D8%A7%D8%B6">
          احصل على الاتجاهات
          <svg aria-hidden="true" style="width:16px;height:16px"><use href="#i-send"></use></svg>
        </a>
      </div>

      <div class="visit__photo">
        <img src="<?php echo tq_site_asset('img/office.webp'); ?>" width="880" height="587" loading="lazy"
             decoding="async" alt="مدخل مقرّ منصة تقدّر بجدار أخضر وزخرفة عربية">
      </div>
    </div>
  </div>
</section>

<!-- ══════════ الأسئلة الشائعة ══════════ -->
<section class="section section--plain" id="faq">
  <div class="shell">
    <div class="section-head">
      <h2><span>الأسئلة الشائعة</span></h2>
      <div class="rule"><svg aria-hidden="true"><use href="#i-star8"></use></svg></div>
    </div>

    <div class="faq-layout">
      <div class="faq-grid" id="faq">
        <div class="faq-item reveal">
          <button class="faq-q" type="button" aria-expanded="false" aria-controls="cfaq-a-1" id="cfaq-q-1">
            كيف يمكنني إنشاء حساب في منصة تقدّر؟
            <svg aria-hidden="true"><use href="#i-plus"></use></svg>
          </button>
          <div class="faq-a" id="cfaq-a-1" role="region" aria-labelledby="cfaq-q-1"><div><p>اضغط على «إنشاء حساب» في أعلى الصفحة، ثم أدخل بياناتك
            وفعّل الحساب من رابط التفعيل الذي يصلك على بريدك. العملية لا تستغرق دقيقتين.</p></div></div>
        </div>
        <div class="faq-item reveal">
          <button class="faq-q" type="button" aria-expanded="false" aria-controls="cfaq-a-2" id="cfaq-q-2">
            هل يمكنني تجربة المنصة قبل الاشتراك؟
            <svg aria-hidden="true"><use href="#i-plus"></use></svg>
          </button>
          <div class="faq-a" id="cfaq-a-2" role="region" aria-labelledby="cfaq-q-2"><div><p>نعم، توفّر المنصة دروسًا مجانية في كل برنامج تعليمي
            لتجربتها قبل اتخاذ قرار الاشتراك.</p></div></div>
        </div>
        <div class="faq-item reveal">
          <button class="faq-q" type="button" aria-expanded="false" aria-controls="cfaq-a-3" id="cfaq-q-3">
            هل المحتوى التعليمي معتمد من جهات رسمية؟
            <svg aria-hidden="true"><use href="#i-plus"></use></svg>
          </button>
          <div class="faq-a" id="cfaq-a-3" role="region" aria-labelledby="cfaq-q-3"><div><p>المحتوى مبنيّ على المناهج السعودية المعتمدة، ويُراجَع
            من معلمين متخصّصين قبل نشره.</p></div></div>
        </div>
        <div class="faq-item reveal">
          <button class="faq-q" type="button" aria-expanded="false" aria-controls="cfaq-a-4" id="cfaq-q-4">
            كيف يمكنني التواصل مع المعلم؟
            <svg aria-hidden="true"><use href="#i-plus"></use></svg>
          </button>
          <div class="faq-a" id="cfaq-a-4" role="region" aria-labelledby="cfaq-q-4"><div><p>من داخل صفحة الدرس تجد زر «تواصل مع المعلّم» لإرسال
            سؤالك مباشرة، ويصلك الردّ في صندوق رسائلك داخل المنصة.</p></div></div>
        </div>
        <div class="faq-item reveal">
          <button class="faq-q" type="button" aria-expanded="false" aria-controls="cfaq-a-5" id="cfaq-q-5">
            هل يمكنني تتبّع تقدّم طفلي في التعلّم؟
            <svg aria-hidden="true"><use href="#i-plus"></use></svg>
          </button>
          <div class="faq-a" id="cfaq-a-5" role="region" aria-labelledby="cfaq-q-5"><div><p>نعم، حساب وليّ الأمر يمنحك لوحة متابعة بتقارير دورية
            عن الدرجات والواجبات والحضور ونقاط القوة والضعف.</p></div></div>
        </div>
        <div class="faq-item reveal">
          <button class="faq-q" type="button" aria-expanded="false" aria-controls="cfaq-a-6" id="cfaq-q-6">
            ما هي طرق الدفع المتاحة؟
            <svg aria-hidden="true"><use href="#i-plus"></use></svg>
          </button>
          <div class="faq-a" id="cfaq-a-6" role="region" aria-labelledby="cfaq-q-6"><div><p>تُقبل مدى والبطاقات الائتمانية وApple&nbsp;Pay،
            مع إمكانية الاشتراك الشهري أو السنوي.</p></div></div>
        </div>
      </div>

      <div class="help-card reveal">
        <span class="ico"><svg aria-hidden="true"><use href="#i-chat"></use></svg></span>
        <h3>لم تجد إجابة لسؤالك؟</h3>
        <p>فريق الدعم لدينا مستعدّ لمساعدتك في أي وقت</p>
        <?php /* كان `#top` ولا عنصر بهذا المعرّف في الصفحة — رابطٌ لا يفعل
        شيئًا. ووجهته الصحيحة نموذجُ المراسلة في الصفحة نفسها. */ ?>
        <a class="btn btn--primary" href="#contact-form">
          <svg aria-hidden="true" style="width:16px;height:16px"><use href="#i-support"></use></svg>
          تواصل معنا الآن
        </a>
      </div>
    </div>
  </div>
</section>
