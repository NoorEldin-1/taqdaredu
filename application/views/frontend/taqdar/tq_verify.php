<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * التحقق من شهادة — صفحة عامة.
 *
 * من يفتحها ليس طالبا ولا معلما: جهة توظيف، أو مدرسة، أو أب يتحقق. ولا
 * حساب له هنا ولا يريد أن ينشئ واحدا. فالصفحة تفتح بلا تسجيل دخول،
 * وتجيب سؤالا واحدا في أول سطر: **هل هذه الشهادة صحيحة؟**
 *
 * **ولا تعرض أكثر مما يلزم التحقق.** لا بريد الطالب، ولا صفه، ولا بقية
 * شهاداته، ولا رابط إلى ملفه. من يحمل رقم شهادة لا يعطى به سجل صاحبها —
 * والتحقق يثبت الوثيقة لا يفتح الملف.
 *
 * وتعرض بغلاف الموقع العام لا بغلاف البوابة: البوابة تفترض جلسة وشريطا
 * جانبيا، وهذه لزائر.
 */
$c = isset($certificate) ? $certificate : null;
$code = isset($cert_code) ? (string) $cert_code : '';
$ok = (bool) $c;

if ($ok) {
    $code = 'TQ-' . str_pad((string) (int) $c['id'], 6, '0', STR_PAD_LEFT);
}
?>

<section class="section">
  <div class="shell shell--auth">
    <div class="tq-vf">

      <a class="tq-vf__brand" href="<?php echo base_url(); ?>" aria-label="<?php echo te('منصة تقدر'); ?>">
        <img src="<?php echo tq_site_asset('img/logo.webp'); ?>" alt="<?php echo te('منصة تقدر'); ?>" width="200" height="112">
      </a>

      <div class="tq-vf__card tq-vf__card--<?php echo $ok ? 'ok' : 'no'; ?>">

        <span class="tq-vf__mark" aria-hidden="true"><?php
          echo $ok ? tq_icon('check-badge', 34) : tq_icon('help', 34); ?></span>

        <h1 class="tq-vf__verdict"><?php
          echo $ok ? t('شهادة صحيحة صادرة من منصة تقدر') : t('لا شهادة بهذا الرمز'); ?></h1>

        <?php if ($ok): ?>

          <dl class="tq-vf__meta">
            <div>
              <dt><?php echo t('حاملها'); ?></dt>
              <dd><?php echo html_escape($c['holder'] ?: t('غير مذكور')); ?></dd>
            </div>
            <div>
              <dt><?php echo t('المحطة'); ?></dt>
              <dd><?php echo html_escape($c['milestone_title'] ?: ($c['path_title'] ?: t('محطة تعليمية'))); ?></dd>
            </div>
            <?php if (!empty($c['path_title']) && !empty($c['milestone_title'])): ?>
              <div>
                <dt><?php echo t('البرنامج'); ?></dt>
                <dd><?php echo html_escape($c['path_title']); ?></dd>
              </div>
            <?php endif; ?>
            <div>
              <dt><?php echo t('نسبة الإتقان'); ?></dt>
              <dd><?php echo tq_num((int) $c['score'] . '%'); ?></dd>
            </div>
            <div>
              <dt><?php echo t('تاريخ الإصدار'); ?></dt>
              <dd><?php echo tq_num(date('Y-m-d', strtotime((string) $c['submitted_at']))); ?></dd>
            </div>
            <div>
              <dt><?php echo t('رمز التحقق'); ?></dt>
              <dd dir="ltr" style="unicode-bidi:isolate"><?php echo html_escape($code); ?></dd>
            </div>
          </dl>

          <p class="tq-vf__how">
            <?php echo t('هذه الشهادة صدرت على'); ?> <strong><?php echo t('إتقان مقاس'); ?></strong> <?php echo t('لا على مشاهدة: أنهى حاملها محطة تعليمية كاملة، واجتاز اختبارها بأسئلة تقيس أهدافها هدفا هدفا.'); ?>
          </p>

        <?php else: ?>

          <p class="tq-vf__how">
            <?php echo t('الرمز'); ?> <span dir="ltr" style="unicode-bidi:isolate"><?php
              echo html_escape($code !== '' ? $code : '—'); ?></span> <?php echo t('لا يقابل شهادة صادرة. راجع الرمز كما هو مكتوب على الوثيقة — وصورته'); ?>
            <span dir="ltr" style="unicode-bidi:isolate">TQ-000000</span>.
          </p>

          <form class="tq-vf__form" method="get" action="<?php echo base_url('verify'); ?>"
                onsubmit="location.href='<?php echo base_url('verify/'); ?>' + encodeURIComponent(this.code.value); return false;">
            <label class="sr-only" for="tqVfCode"><?php echo t('رمز التحقق'); ?></label>
            <input id="tqVfCode" name="code" type="text" dir="ltr" placeholder="TQ-000000"
                   autocomplete="off" spellcheck="false" required>
            <button class="btn btn--primary" type="submit"><?php echo t('تحقق'); ?></button>
          </form>

        <?php endif; ?>
      </div>

      <p class="tq-vf__foot">
        <a href="<?php echo base_url(); ?>"><?php echo t('منصة تقدر'); ?></a> <?php echo t('· منصة تعليمية سعودية'); ?>
      </p>
    </div>
  </div>
</section>

<?php /* TQ-VERIFY-TOKENS — والورقة تكتب بتوكنات **الموقع** لا البوابة.

   الصفحة معلنة في `$tq_public_tq_pages` أنها صفحة موقع لا بوابة (وهو
   صواب: يفتحها من لا حساب له)، فتحمل معها `taqdar.css` و`pages.css`
   وحدهما — و`tokens.css` لا تحمل. وكانت هذه الكتلة مكتوبة كلها بـ
   `--tq-surface` و`--tq-line` و`--tq-space-*`: توكنات لا وجود لها هنا.

   وقيمة مخصصة غير معرفة **تبطل التصريح كله** عند حساب القيمة، فتسقط
   الخلفية والحد والحشوة ونصف القطر بلا خطأ يظهر: تفتح جهة توظيف رابط
   شهادة فتقرأ نصا عاريا على ورق، بلا بطاقة ولا صندوق إدخال ولا زر —
   وهي أول ما يقاس به صدق الوثيقة. وهو ما تحذر منه `shell.css` حرفا:
   «توكن غير معرف يعطي لونا شفافا بلا خطأ ينبه».

   والمخرج ليس حقن `tokens.css` هنا — ذلك يجر `h1{font:...}` وأخواتها
   على صفحة موقع — بل أن تتكلم الصفحة لهجة سطحها. والزر صار `.btn`
   (طراز الموقع) لا `.tq-btn` (طراز البوابة): ذاك معرف في
   `components.css` وهي لا تحمل هنا كذلك. */ ?>
<style>
.tq-vf { max-inline-size: 560px; margin-inline: auto; text-align: center; }
.tq-vf__brand { display: inline-block; margin-block-end: 40px; }
.tq-vf__brand img { block-size: auto; inline-size: 180px; }

.tq-vf__card {
  background: var(--paper); border: 1px solid var(--gold-line);
  border-radius: var(--r-card);
  padding: clamp(26px, 4vw, 40px) clamp(16px, 2.4vw, 24px);
  border-block-start: 5px solid var(--gold-line);
  box-shadow: var(--sh-soft);
}
.tq-vf__card--ok { border-block-start-color: var(--emerald); }
.tq-vf__card--no { border-block-start-color: var(--gold); }

.tq-vf__mark {
  display: grid; place-items: center;
  inline-size: 72px; block-size: 72px; border-radius: 999px;
  margin-inline: auto; margin-block-end: 16px;
}
.tq-vf__card--ok .tq-vf__mark { background: #E8F4F1; color: #0A6157; }
.tq-vf__card--no .tq-vf__mark { background: var(--beige); color: var(--gold-text); }

.tq-vf__verdict {
  font-size: clamp(1.15rem, 3.4vw, 1.5rem); font-weight: 800;
  margin: 0 0 20px; text-wrap: balance; color: var(--ink);
}

.tq-vf__meta {
  display: grid; gap: 12px; margin: 0 0 20px;
  text-align: start;
}
.tq-vf__meta div {
  display: flex; flex-wrap: wrap; gap: 8px;
  justify-content: space-between; align-items: baseline;
  padding-block-end: 8px; border-block-end: 1px dashed var(--gold-line-soft);
}
.tq-vf__meta dt { color: var(--ink-3); font-size: max(12px, .84rem); }
.tq-vf__meta dd { margin: 0; font-weight: 700; color: var(--ink); }

.tq-vf__how { color: var(--ink-2); font-size: max(12.5px, .9rem); margin: 0; line-height: 1.85; }

.tq-vf__form {
  display: flex; flex-wrap: wrap; gap: 8px;
  justify-content: center; margin-block-start: 20px;
}
/* والحقل `flex:1` بأساس ١٨٠px: على عرض ٣٤٤ يبقى الصندوق والزر في صف
   واحد بلا أن ينكمش أحدهما تحت ما يقرأ فيه. */
.tq-vf__form input {
  flex: 1 1 140px; min-inline-size: 0; font: inherit;
  border: 1px solid var(--gold-line); border-radius: 14px;
  padding: 12px; background: var(--cream); color: var(--ink);
  text-align: center; letter-spacing: 1px;
}
.tq-vf__form input:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201,165,95,.16); }

.tq-vf__foot { margin-block-start: 20px; font-size: max(12px, .82rem); color: var(--ink-3); }
.tq-vf__foot a { color: var(--gold-text); font-weight: 700 }
</style>
