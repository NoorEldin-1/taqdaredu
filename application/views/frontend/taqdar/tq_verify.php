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

      <a class="tq-vf__brand" href="<?php echo base_url(); ?>" aria-label="منصة تقدر">
        <img src="<?php echo tq_site_asset('img/logo.webp'); ?>" alt="منصة تقدر" width="200" height="112">
      </a>

      <div class="tq-vf__card tq-vf__card--<?php echo $ok ? 'ok' : 'no'; ?>">

        <span class="tq-vf__mark" aria-hidden="true"><?php
          echo $ok ? tq_icon('check-badge', 34) : tq_icon('help', 34); ?></span>

        <h1 class="tq-vf__verdict"><?php
          echo $ok ? 'شهادة صحيحة صادرة من منصة تقدر' : 'لا شهادة بهذا الرمز'; ?></h1>

        <?php if ($ok): ?>

          <dl class="tq-vf__meta">
            <div>
              <dt>حاملها</dt>
              <dd><?php echo html_escape($c['holder'] ?: 'غير مذكور'); ?></dd>
            </div>
            <div>
              <dt>المحطة</dt>
              <dd><?php echo html_escape($c['milestone_title'] ?: ($c['path_title'] ?: 'محطة تعليمية')); ?></dd>
            </div>
            <?php if (!empty($c['path_title']) && !empty($c['milestone_title'])): ?>
              <div>
                <dt>البرنامج</dt>
                <dd><?php echo html_escape($c['path_title']); ?></dd>
              </div>
            <?php endif; ?>
            <div>
              <dt>نسبة الإتقان</dt>
              <dd><?php echo tq_num((int) $c['score'] . '%'); ?></dd>
            </div>
            <div>
              <dt>تاريخ الإصدار</dt>
              <dd><?php echo tq_num(date('Y-m-d', strtotime((string) $c['submitted_at']))); ?></dd>
            </div>
            <div>
              <dt>رمز التحقق</dt>
              <dd dir="ltr" style="unicode-bidi:isolate"><?php echo html_escape($code); ?></dd>
            </div>
          </dl>

          <p class="tq-vf__how">
            هذه الشهادة صدرت على <strong>إتقان مقاس</strong> لا على مشاهدة: أنهى حاملها
            محطة تعليمية كاملة، واجتاز اختبارها بأسئلة تقيس أهدافها هدفا هدفا.
          </p>

        <?php else: ?>

          <p class="tq-vf__how">
            الرمز <span dir="ltr" style="unicode-bidi:isolate"><?php
              echo html_escape($code !== '' ? $code : '—'); ?></span> لا يقابل شهادة صادرة.
            راجع الرمز كما هو مكتوب على الوثيقة — وصورته
            <span dir="ltr" style="unicode-bidi:isolate">TQ-000000</span>.
          </p>

          <form class="tq-vf__form" method="get" action="<?php echo base_url('verify'); ?>"
                onsubmit="location.href='<?php echo base_url('verify/'); ?>' + encodeURIComponent(this.code.value); return false;">
            <label class="sr-only" for="tqVfCode">رمز التحقق</label>
            <input id="tqVfCode" name="code" type="text" dir="ltr" placeholder="TQ-000000"
                   autocomplete="off" spellcheck="false" required>
            <button class="tq-btn tq-btn--primary" type="submit">تحقق</button>
          </form>

        <?php endif; ?>
      </div>

      <p class="tq-vf__foot">
        <a href="<?php echo base_url(); ?>">منصة تقدر</a> · منصة تعليمية سعودية
      </p>
    </div>
  </div>
</section>

<style>
.tq-vf { max-inline-size: 560px; margin-inline: auto; text-align: center; }
.tq-vf__brand { display: inline-block; margin-block-end: var(--tq-space-h2); }
.tq-vf__brand img { block-size: auto; inline-size: 180px; }

.tq-vf__card {
  background: var(--tq-surface); border: 1px solid var(--tq-line);
  border-radius: var(--tq-radius-card);
  padding: var(--tq-space-h2) var(--tq-space-xl);
  border-block-start: 5px solid var(--tq-line);
}
.tq-vf__card--ok { border-block-start-color: var(--tq-actionMastery); }
.tq-vf__card--no { border-block-start-color: var(--tq-amber); }

.tq-vf__mark {
  display: grid; place-items: center;
  inline-size: 72px; block-size: 72px; border-radius: var(--tq-radius-pill);
  margin-inline: auto; margin-block-end: var(--tq-space-l);
}
.tq-vf__card--ok .tq-vf__mark { background: var(--tq-mint-fill);  color: var(--tq-mint-ink); }
.tq-vf__card--no .tq-vf__mark { background: var(--tq-peach-fill); color: var(--tq-peach-ink); }

.tq-vf__verdict {
  font-size: clamp(1.15rem, 3.4vw, 1.5rem); font-weight: 800;
  margin: 0 0 var(--tq-space-xl); text-wrap: balance;
}
.tq-vf__card--ok .tq-vf__verdict { color: var(--tq-navy); }

.tq-vf__meta {
  display: grid; gap: var(--tq-space-m); margin: 0 0 var(--tq-space-xl);
  text-align: start;
}
.tq-vf__meta div {
  display: flex; flex-wrap: wrap; gap: var(--tq-space-s);
  justify-content: space-between; align-items: baseline;
  padding-block-end: var(--tq-space-s); border-block-end: 1px dashed var(--tq-line);
}
.tq-vf__meta dt { color: var(--tq-text3); font-size: .84rem; }
.tq-vf__meta dd { margin: 0; font-weight: 700; }

.tq-vf__how { color: var(--tq-text2); font-size: .9rem; margin: 0; }

.tq-vf__form {
  display: flex; flex-wrap: wrap; gap: var(--tq-space-s);
  justify-content: center; margin-block-start: var(--tq-space-xl);
}
.tq-vf__form input {
  flex: 1; min-inline-size: 180px; font: inherit;
  border: 1px solid var(--tq-line); border-radius: var(--tq-radius-small);
  padding: var(--tq-space-m); background: var(--tq-surface); color: inherit;
  text-align: center; letter-spacing: 1px;
}
.tq-vf__form input:focus { outline: none; border-color: var(--tq-teal); }

.tq-vf__foot { margin-block-start: var(--tq-space-xl); font-size: .82rem; color: var(--tq-text3); }
</style>
