<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * خريطة الإتقان — في شاشة واحدة، كما تشترط `F4.4`.
 *
 * محركها `Taqdar_repo_model::get_skill_map()` مبني ويعمل، و
 * `taqdar_gate/skill_map` منشور، واللوحة الإدارية تستهلكه في
 * `taqdar_admin/mastery`. ولم يكن لصاحب الخريطة موضع يراها فيه: تقاس
 * عليه، وتصدر بها شهادته، ولا تعرض عليه.
 *
 * وترتيب العرض **بالأضعف أولا** لا بالأقوى: الشاشة أداة عمل لا لوحة
 * فخر — من يفتحها يريد أن يعرف أين يذهب، ومن رتبها بالأقوى قرأ سطرين
 * ثم أغلق.
 *
 * **ولا مقارنة بأحد.** لا ترتيب، ولا متوسط الطلاب، ولا «أنت أفضل من ٪٦٠».
 * وهذه قاعدة الحماية في وثيقة المنتج، وهي في بوابة الطالب كما هي في
 * بوابة وليه.
 */
include 'tq_student_styles.php';

$tq_nav   = 'mastery';
$tq_role  = 'student';
$tq_title = t('خريطة إتقاني');
$tq_sub   = t('كل هدف تعلمته ومستواك فيه — مرتبا بما يحتاج عملك أولا.');
$tq_icon  = 'target';

include 'portal_open.php';
?>

<div class="tq-mastery" data-tq-mastery
     data-tq-gate="<?php echo base_url('taqdar_gate'); ?>"
     data-tq-lesson-base="<?php echo base_url('student/lesson'); ?>">

  <div data-tq-ms-skeleton>
    <div class="tq-skeleton tq-skeleton--card" style="block-size:120px"></div>
    <div class="tq-skeleton tq-skeleton--card" style="block-size:280px;margin-block-start:var(--tq-space-xl)"></div>
  </div>

  <div class="tq-card" data-tq-ms-error hidden>
    <div class="tq-empty">
      <span class="tq-s-art tq-pastel tq-pastel--peach" aria-hidden="true">
        <span class="tq-pastel__icon"><?php echo tq_icon('target', 34); ?></span>
      </span>
      <h3 class="tq-empty__title" data-tq-ms-error-msg><?php echo t('تعذر تحميل خريطتك'); ?></h3>
      <button class="tq-btn tq-btn--secondary" type="button" data-tq-ms-retry><?php echo t('إعادة المحاولة'); ?></button>
    </div>
  </div>

  <div data-tq-ms-body hidden>

    <section class="tq-card" data-tq-ms-empty hidden>
      <?php echo tq_s_empty('target', 'sand', t('خريطتك تبدأ بأول درس'),
            t('تقاس مستوياتك من إجاباتك — على أسئلة الدرس وعلى المراجعة. أكمل درسا واحدا وستظهر أهدافه هنا.'),
            t('ابدأ درسا'), base_url('student/lessons'), false, 'primary'); ?>
    </section>

    <div data-tq-ms-has hidden>

      <section class="tq-s-grid3" style="margin-block-end:var(--tq-space-xl)" data-tq-ms-stats></section>

      <!-- أضعف خمسة: هذا هو الجواب العملي، فيسبق الخريطة كلها -->
      <section class="tq-card" data-tq-ms-weak-card hidden style="margin-block-end:var(--tq-space-l)">
        <div class="tq-card__head">
          <h2 class="tq-card__title"><?php echo t('ابدأ من هنا'); ?></h2>
          <span class="tq-caption"><?php echo t('أضعف أهدافك — وأعلاها عائدا على وقتك'); ?></span>
        </div>
        <div class="tq-ms-weak" data-tq-ms-weak></div>
      </section>

      <section class="tq-card">
        <div class="tq-card__head">
          <h2 class="tq-card__title"><?php echo t('الخريطة كاملة'); ?></h2>
          <div class="tq-row" style="gap:var(--tq-space-s)">
            <label class="sr-only" for="tqMsFilter"><?php echo t('رشح بالكورس'); ?></label>
            <select class="tq-select" id="tqMsFilter" data-tq-ms-filter>
              <option value=""><?php echo t('كل الكورسات'); ?></option>
            </select>
          </div>
        </div>
        <div class="tq-ms-list" data-tq-ms-list></div>
      </section>

    </div>
  </div>
</div>

<style>
.tq-ms-weak { display: flex; flex-direction: column; gap: var(--tq-space-s); }

.tq-ms-row {
  display: flex; flex-wrap: wrap; gap: var(--tq-space-m); align-items: center;
  padding: var(--tq-space-m) var(--tq-space-l);
  border: 1px solid var(--tq-line); border-radius: var(--tq-radius-small);
  background: var(--tq-surface);
}
.tq-ms-row__main { flex: 1; min-inline-size: 180px; display: flex; flex-direction: column; gap: 2px; }
.tq-ms-row__t { font-weight: 600; }
.tq-ms-row__w { font-size: .82rem; color: var(--tq-text3); }

/* الشريط والرقم معا: الشريط يقرأ بلمحة والرقم يقرأ بدقة، وأحدهما
   وحده يترك أحد القارئين. والعتبات ثلاث لأن «ضعيف» و«قوي» لا تكفيان
   لمن هو في السبعين: عنده عمل باق، وليس متعثرا. */
.tq-ms-bar {
  inline-size: 160px; block-size: var(--tq-progress-h);
  background: var(--tq-line); border-radius: var(--tq-radius-pill); overflow: hidden; flex: none;
}
.tq-ms-bar span { display: block; block-size: 100%; border-radius: var(--tq-radius-pill); }
.tq-ms-bar--low  span { background: var(--tq-danger); }
.tq-ms-bar--mid  span { background: var(--tq-amber); }
.tq-ms-bar--high span { background: var(--tq-actionMastery); }

.tq-ms-pct {
  font-variant-numeric: tabular-nums; font-weight: 800; min-inline-size: 48px;
  text-align: center; unicode-bidi: isolate; direction: ltr;
}
.tq-ms-pct--low  { color: var(--tq-danger); }
.tq-ms-pct--mid  { color: var(--tq-amber); }
.tq-ms-pct--high { color: var(--tq-actionMastery); }

.tq-ms-group { margin-block-end: var(--tq-space-xl); }
.tq-ms-group__h {
  font-weight: 700; color: var(--tq-text2);
  padding-block-end: var(--tq-space-s); margin-block-end: var(--tq-space-s);
  border-block-end: 1px solid var(--tq-line);
}

@media (max-width: 640px) { .tq-ms-bar { inline-size: 100%; } }
</style>

<script>
(function () {
  var root = document.querySelector('[data-tq-mastery]');
  if (!root) return;

  var GATE = root.getAttribute('data-tq-gate');
  var LESSON = root.getAttribute('data-tq-lesson-base');
  var $ = function (s) { return root.querySelector(s); };
  var show = function (el, on) { if (el) el.hidden = !on; };

  var el = {
    skeleton: $('[data-tq-ms-skeleton]'), error: $('[data-tq-ms-error]'),
    errorMsg: $('[data-tq-ms-error-msg]'), body: $('[data-tq-ms-body]'),
    empty: $('[data-tq-ms-empty]'), has: $('[data-tq-ms-has]'),
    stats: $('[data-tq-ms-stats]'), weakCard: $('[data-tq-ms-weak-card]'),
    weak: $('[data-tq-ms-weak]'), list: $('[data-tq-ms-list]'),
    filter: $('[data-tq-ms-filter]')
  };

  var rows = [];

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* العتبات مكتوبة مرة واحدة: الشريط والرقم والتسمية تقرأ منها كلها،
     فلا يقول أحدها «يحتاج عملا» والآخر يلونه أخضر. */
  function band(level) {
    if (level >= 80) return { k: 'high', label: 'أتقنته' };
    if (level >= 50) return { k: 'mid',  label: 'يحتاج تثبيتا' };
    return { k: 'low', label: 'يحتاج عملا' };
  }

  function api(p) {
    /* TQ-RAW-ERROR — الغلاف الواحد في `tq-i18n.js`. */
    return TQ.gateFetch(GATE + '/' + p, {
      credentials: 'same-origin', headers: { 'Accept': 'application/json' }
    });
  }

  function rowHtml(o) {
    var lvl = Math.round(o.level || 0);
    var b = band(lvl);
    var where = [o.course_title, o.lesson_title].filter(Boolean).map(esc).join(' · ');
    var link = (o.lesson_id && o.course_id)
      ? '<a class="tq-btn tq-btn--ghost tq-btn--sm" href="' + LESSON + '/' + o.course_id + '/' + o.lesson_id +
        (o.at_second ? '?t=' + o.at_second : '') + '">راجع</a>'
      : '';

    return '<div class="tq-ms-row">' +
             '<div class="tq-ms-row__main">' +
               '<span class="tq-ms-row__t">' + esc(o.objective_text || 'هدف') + '</span>' +
               (where ? '<span class="tq-ms-row__w">' + where + ' · ' + b.label + '</span>'
                      : '<span class="tq-ms-row__w">' + b.label + '</span>') +
             '</div>' +
             '<div class="tq-ms-bar tq-ms-bar--' + b.k + '" role="progressbar" aria-valuenow="' + lvl +
               '" aria-valuemin="0" aria-valuemax="100"><span style="inline-size:' + lvl + '%"></span></div>' +
             '<span class="tq-ms-pct tq-ms-pct--' + b.k + '">' + lvl + '%</span>' +
             link +
           '</div>';
  }

  function render(d) {
    rows = (d && d.objectives) || [];

    if (!rows.length) { show(el.empty, true); show(el.has, false); return; }
    show(el.empty, false); show(el.has, true);

    var avg = Math.round(d.average_level || 0);
    var mastered = rows.filter(function (o) { return (o.level || 0) >= 80; }).length;
    var needs = rows.filter(function (o) { return (o.level || 0) < 50; }).length;

    el.stats.innerHTML =
      st(avg + '%', 'متوسط إتقانك', 'mint') +
      st(mastered + ' من ' + rows.length, 'هدفا أتقنته', 'sky') +
      st(needs, 'هدفا يحتاج عملا', needs ? 'peach' : 'mint');

    var weak = (d.weakest || []).filter(function (o) { return (o.level || 0) < 80; });
    show(el.weakCard, weak.length > 0);
    if (weak.length) el.weak.innerHTML = weak.map(rowHtml).join('');

    var seen = {}, opts = '';
    rows.forEach(function (o) {
      if (o.course_title && !seen[o.course_title]) {
        seen[o.course_title] = 1;
        opts += '<option value="' + esc(o.course_title) + '">' + esc(o.course_title) + '</option>';
      }
    });
    if (el.filter && !el.filter.dataset.built) {
      el.filter.insertAdjacentHTML('beforeend', opts);
      el.filter.dataset.built = '1';
    }

    renderList();
  }

  function renderList() {
    var f = el.filter ? el.filter.value : '';
    var view = f ? rows.filter(function (o) { return o.course_title === f; }) : rows;

    /* التجميع بالكورس: قائمة من ثمانين هدفا بلا عناوين تقرأ سطرا سطرا
       ولا تفهم. والعنوان يعطي القارئ موضعه. */
    var groups = {}, order = [];
    view.forEach(function (o) {
      var k = o.course_title || 'أهداف أخرى';
      if (!groups[k]) { groups[k] = []; order.push(k); }
      groups[k].push(o);
    });

    el.list.innerHTML = order.map(function (k) {
      return '<div class="tq-ms-group"><div class="tq-ms-group__h">' + esc(k) + '</div>' +
             groups[k].map(rowHtml).join('') + '</div>';
    }).join('') || '<p class="tq-caption" style="text-align:center;padding-block:var(--tq-space-xl)">لا أهداف في هذا الكورس بعد.</p>';
  }

  function st(v, label, pastel) {
    return '<div class="tq-s-stat tq-pastel tq-pastel--' + pastel + '">' +
           '<span class="tq-s-stat__value tq-pastel__title">' + esc(v) + '</span>' +
           '<span class="tq-s-stat__label tq-pastel__body">' + esc(label) + '</span></div>';
  }

  function load() {
    show(el.skeleton, true); show(el.error, false); show(el.body, false);
    api('skill_map').then(function (d) {
      show(el.skeleton, false); show(el.body, true); render(d);
    }).catch(function (e) {
      show(el.skeleton, false); show(el.error, true);
      if (el.errorMsg) el.errorMsg.textContent = e.message;
    });
  }

  if (el.filter) el.filter.addEventListener('change', renderList);
  root.addEventListener('click', function (e) {
    if (e.target.closest('[data-tq-ms-retry]')) load();
  });

  load();
})();
</script>

<?php include 'portal_close.php'; ?>
