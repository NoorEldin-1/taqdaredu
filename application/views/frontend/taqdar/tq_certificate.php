<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * الشهادة — الوثيقة التي يريها صاحبها لغيره.
 *
 * كانت `Taqdar::certificate()` تسقط إلى عرض احتياطي مكتوب داخل المتحكم
 * لأن هذا الملف لم يوجد: صفحة قائمة بذاتها بأنماط مضمنة، لا شهادة تطبع
 * ولا تشارك. وهذه هي الشاشة الناقصة.
 *
 * **ورمز التحقق أهم ما فيها** — `B4.7`. الشهادة التي لا يتحقق منها أحد
 * ورقة ملونة: فالرمز مطبوع نصا **ورمزا مصورا** معا، والاثنان يقودان إلى
 * صفحة عامة تفتح بلا حساب. والنص للمن ينسخ، والرمز لمن يصور.
 *
 * **والطباعة تصمم لا تترك للمتصفح**: من يطبعها يريد الشهادة وحدها، لا
 * الشريط الجانبي وترويسة البوابة معها. و`@media print` أدناه هي الفرق
 * بين وثيقة وصفحة موقع مطبوعة.
 */
include 'tq_student_styles.php';

$tq_nav   = 'certificates';
$tq_role  = function_exists('tq_role') ? tq_role() : 'student';
$tq_title = '';

$c = isset($certificate) ? $certificate : null;
$tq_code = $c ? 'TQ-' . str_pad((string) (int) $c['id'], 6, '0', STR_PAD_LEFT) : '';

include 'portal_open.php';
?>

<?php if (!$c): ?>

  <section class="tq-card">
    <?php echo tq_s_empty('award', 'sand', 'لا شهادة بهذا الرقم',
          'الشهادة تصدر على إتقان مقاس لا على مشاهدة: تنهي المحطة، وتجتاز اختبارها، فتصدر باسمك ورمز تحققها.',
          'شهاداتي', base_url('student/certificates'), false, 'primary'); ?>
  </section>

<?php else: ?>

  <div class="tq-cert-wrap">

    <article class="tq-cert" id="tqCert">
      <header class="tq-cert__top">
        <img class="tq-cert__logo" src="<?php echo tq_site_asset('img/logo.webp'); ?>"
             alt="منصة تقدر" width="180" height="101">
        <span class="tq-cert__kind">شهادة إتقان</span>
      </header>

      <div class="tq-cert__body">
        <p class="tq-cert__lead">تشهد منصة تقدر بأن</p>
        <h1 class="tq-cert__name"><?php echo html_escape($c['holder'] ?: 'الطالب'); ?></h1>
        <p class="tq-cert__lead">قد أتقن</p>
        <h2 class="tq-cert__subject"><?php
          echo html_escape($c['milestone_title'] ?: ($c['path_title'] ?: 'محطة تعليمية')); ?></h2>

        <?php if (!empty($c['path_title']) && !empty($c['milestone_title'])): ?>
          <p class="tq-cert__path">ضمن برنامج <?php echo html_escape($c['path_title']); ?></p>
        <?php endif; ?>

        <dl class="tq-cert__meta">
          <div>
            <dt>نسبة الإتقان</dt>
            <dd><?php echo tq_num((int) $c['score'] . '%'); ?></dd>
          </div>
          <div>
            <dt>تاريخ الإصدار</dt>
            <dd><?php echo tq_num(tq_s_date(strtotime((string) $c['submitted_at']))); ?></dd>
          </div>
          <div>
            <dt>رمز التحقق</dt>
            <dd class="tq-cert__code"><?php echo html_escape($tq_code); ?></dd>
          </div>
        </dl>
      </div>

      <footer class="tq-cert__foot">
        <?php /* الرمز المصور: مصدره مسار يولد PNG عند الطلب. و`alt`
                يحمل الرابط نصا فمن لا يرى الصورة يقرأ الوجهة. */ ?>
        <img class="tq-cert__qr"
             src="<?php echo base_url('student/certificate/' . (int) $c['id'] . '/qr'); ?>"
             alt="رمز التحقق — <?php echo html_escape(base_url('verify/' . $tq_code)); ?>"
             width="120" height="120" loading="lazy">
        <div class="tq-cert__verify">
          <p><strong>تحقق من هذه الشهادة</strong></p>
          <p>امسح الرمز، أو افتح<br>
            <span class="tq-cert__url" dir="ltr"><?php echo html_escape(base_url('verify/' . $tq_code)); ?></span>
          </p>
          <p class="tq-cert__note">
            الشهادة تصدر على إتقان مقاس بأسئلة تقيس الأهداف، لا على مشاهدة.
          </p>
        </div>
      </footer>
    </article>

    <div class="tq-cert__acts">
      <button class="tq-btn tq-btn--primary" type="button" onclick="window.print()">اطبع أو احفظ PDF</button>
      <a class="tq-btn tq-btn--secondary" href="<?php echo base_url('verify/' . $tq_code); ?>"
         target="_blank" rel="noopener">افتح صفحة التحقق</a>
      <a class="tq-btn tq-btn--ghost" href="<?php echo base_url('student/certificates'); ?>">كل شهاداتي</a>
    </div>
  </div>

<?php endif; ?>

<style>
.tq-cert-wrap { max-inline-size: 820px; margin-inline: auto; }

.tq-cert {
  background: var(--tq-surface);
  border: 1px solid var(--tq-line);
  border-radius: var(--tq-radius-card);
  padding: var(--tq-space-h3) var(--tq-space-h2);
  text-align: center;
  position: relative; overflow: hidden;
}
/* شريط الهوية أعلى الوثيقة — ثلاثة ألوان العلامة، لا زخرفة عامة */
.tq-cert::before {
  content: ""; position: absolute; inset-block-start: 0; inset-inline: 0; block-size: 6px;
  background: linear-gradient(90deg, var(--tq-navy), var(--tq-teal), var(--tq-amber));
}

.tq-cert__top {
  display: flex; flex-direction: column; align-items: center; gap: var(--tq-space-m);
  margin-block-end: var(--tq-space-h2);
}
.tq-cert__logo { block-size: auto; inline-size: 160px; }
.tq-cert__kind {
  font-size: .8rem; font-weight: 700; color: var(--tq-teal);
  padding: 4px 14px; border-radius: var(--tq-radius-pill); background: var(--tq-mint-fill);
}

.tq-cert__lead { color: var(--tq-text2); margin: 0; font-size: .95rem; }
.tq-cert__name {
  font-size: clamp(1.6rem, 4vw, 2.4rem); font-weight: 800;
  color: var(--tq-navy); margin: var(--tq-space-s) 0 var(--tq-space-l);
}
.tq-cert__subject {
  font-size: clamp(1.1rem, 3vw, 1.5rem); font-weight: 700;
  color: var(--tq-teal); margin: var(--tq-space-s) 0 0;
}
.tq-cert__path { color: var(--tq-text3); font-size: .88rem; margin: var(--tq-space-xs) 0 0; }

.tq-cert__meta {
  display: flex; flex-wrap: wrap; justify-content: center; gap: var(--tq-space-h2);
  margin: var(--tq-space-h2) 0 0; padding: 0;
}
.tq-cert__meta div { display: flex; flex-direction: column; gap: 2px; }
.tq-cert__meta dt { font-size: .76rem; color: var(--tq-text3); }
.tq-cert__meta dd { margin: 0; font-weight: 800; font-size: 1.05rem; }
.tq-cert__code { unicode-bidi: isolate; direction: ltr; letter-spacing: .5px; }

.tq-cert__foot {
  display: flex; flex-wrap: wrap; gap: var(--tq-space-xl); align-items: center;
  justify-content: center; text-align: start;
  margin-block-start: var(--tq-space-h3);
  padding-block-start: var(--tq-space-xl);
  border-block-start: 1px solid var(--tq-line);
}
.tq-cert__qr { flex: none; border-radius: var(--tq-radius-small); background: #fff; }
.tq-cert__verify p { margin: 0 0 4px; font-size: .86rem; color: var(--tq-text2); }
.tq-cert__url { font-size: .8rem; color: var(--tq-teal); unicode-bidi: isolate; }
.tq-cert__note { color: var(--tq-text3); font-size: .78rem; margin-block-start: var(--tq-space-s); }

.tq-cert__acts {
  display: flex; flex-wrap: wrap; gap: var(--tq-space-m);
  justify-content: center; margin-block-start: var(--tq-space-xl);
}

/* الطباعة: الوثيقة وحدها.
   والألوان تثبت صراحة — الطباعة تتجاهل المتغيرات المعتمدة على السمة،
   فشهادة تطبع من واجهة داكنة تخرج بنص أبيض على ورق أبيض. */
@media print {
  .tq-shell__main > *:not(.tq-page),
  .tq-rail, .tq-topbar, .tq-pagehead, .tq-cert__acts, .tq-flash { display: none !important; }
  body, .tq-shell, .tq-shell__main, .tq-page { background: #fff !important; }
  .tq-cert {
    border: 0; box-shadow: none; padding: 24px;
    background: #fff !important; color: #1A2430 !important;
  }
  .tq-cert__name    { color: #023331 !important; }
  .tq-cert__subject { color: #0C786C !important; }
  .tq-cert__kind    { background: #E8F4F1 !important; color: #0A6157 !important; }
  .tq-cert__meta dt, .tq-cert__note, .tq-cert__path { color: #5A6672 !important; }
  @page { margin: 12mm; }
}
</style>

<?php include 'portal_close.php'; ?>
