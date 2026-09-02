<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * دفتر الأخطاء — الباب الذي لم يكن.
 *
 * `Taqdar_repo_model::get_mistakes()` مكتوب ويعمل منذ أن كتبت بوابة
 * الإتقان، و`taqdar_gate/mistakes` منشور وجاهز. ولم يكن في الواجهة كلها
 * سطر واحد يناديهما: لا قالب، ولا بند في الشريط، ولا سطر جافاسكربت.
 * ميزة كاملة مبنية ومختبرة لا يصل إليها طالب واحد. هذا هو القالب الناقص.
 *
 * وشاشتان في واحدة:
 *   **الدفتر** — كل خطأ مصنفا بالمادة والدرس والمفهوم، مرتبا بالأكثر
 *                تكرارا. ومنه رابط إلى ثانية الشرح في الدرس نفسه.
 *   **التدريب المركز** — يسأل الأخطاء سؤالا سؤالا، والصواب فيه يحرك
 *                حالة المهارة ويباعد الموعد إن كان السؤال مجدولا.
 *                فالتدريب يحسب كما تحسب المراجعة ولا يكون عملا بلا أثر.
 *
 * ولا استعلام هنا ولا تصحيح: كل شيء من `taqdar_gate`. الصفحة لا تعرف
 * الإجابة الصحيحة أبدا — تعرضها بعد أن يقولها الخادم. ولو حسبت هنا
 * لأمكن «إتقان» الدفتر بتعديل جافاسكربت.
 */
include 'tq_student_styles.php';

$tq_nav   = 'mistakes';
$tq_role  = 'student';
$tq_title = t('دفتر الأخطاء');
$tq_sub   = t('كل إجابة أخطأتها، مصنفة بمادتها ودرسها ومفهومها. وهنا تدربها حتى تخرج منه.');
$tq_icon  = 'help';

include 'portal_open.php';
?>

<div class="tq-mistakes" data-tq-mistakes
     data-tq-gate="<?php echo base_url('taqdar_gate'); ?>"
     data-tq-lesson-base="<?php echo base_url('student/lesson'); ?>">

  <!-- التحميل: هيكل بشكل المحتوى القادم -->
  <div data-tq-mk-skeleton>
    <div class="tq-skeleton tq-skeleton--card" style="block-size:120px"></div>
    <div class="tq-skeleton tq-skeleton--title" style="margin-block-start:var(--tq-space-xl)"></div>
    <div class="tq-skeleton tq-skeleton--card" style="block-size:220px;margin-block-start:var(--tq-space-m)"></div>
  </div>

  <!-- الخطأ: ما حدث وزر إعادة. والتفصيل في السجل لا على الشاشة -->
  <div class="tq-card" data-tq-mk-error hidden>
    <div class="tq-empty">
      <span class="tq-s-art tq-pastel tq-pastel--peach" aria-hidden="true">
        <span class="tq-pastel__icon"><?php echo tq_icon('help', 34); ?></span>
      </span>
      <h3 class="tq-empty__title" data-tq-mk-error-msg><?php echo t('تعذر تحميل دفترك'); ?></h3>
      <button class="tq-btn tq-btn--secondary" type="button" data-tq-mk-retry><?php echo t('إعادة المحاولة'); ?></button>
    </div>
  </div>

  <div data-tq-mk-body hidden>

    <!-- لا خطأ: نجاح لا فراغ، فيقال بلغة النجاح -->
    <section class="tq-card" data-tq-mk-empty hidden>
      <?php echo tq_s_empty('check-badge', 'mint', t('دفترك نظيف'),
            t('لا إجابة خاطئة مسجلة عليك بعد. تابع دروسك — وما تخطئه سيظهر هنا لتتدرب عليه، لا لتحاسب به.'),
            t('تابع دروسك'), base_url('student/lessons'), false, 'primary'); ?>
    </section>

    <div data-tq-mk-has hidden>

      <!-- الشريط: كم خطأ، وفي كم مفهوم، وكم منها مجدول -->
      <section class="tq-s-grid4" data-tq-mk-stats
               style="margin-block-end:var(--tq-space-xl)"></section>

      <!-- الدفتر -->
      <section class="tq-card" data-tq-mk-list-card>
        <div class="tq-card__head">
          <h2 class="tq-card__title"><?php echo t('أخطاؤك'); ?></h2>
          <div class="tq-row" style="gap:var(--tq-space-s)">
            <label class="sr-only" for="tqMkFilter"><?php echo t('رشح بالمادة'); ?></label>
            <select class="tq-select" id="tqMkFilter" data-tq-mk-filter>
              <option value=""><?php echo t('كل المواد'); ?></option>
            </select>
            <button class="tq-btn tq-btn--primary tq-btn--sm" type="button" data-tq-mk-start>
              <?php echo t('ابدأ تدريبا مركزا'); ?>
            </button>
          </div>
        </div>

        <div class="tq-mk-list" data-tq-mk-list></div>

        <p class="tq-caption" data-tq-mk-filtered-empty hidden
           style="text-align:center;padding-block:var(--tq-space-xl)">
          <?php echo t('لا أخطاء في هذه المادة. اختر مادة أخرى أو اعرض الكل.'); ?>
        </p>
      </section>
    </div>

    <!-- ── التدريب المركز ─────────────────────────────────────────── -->
    <section class="tq-card" data-tq-mk-drill hidden>
      <div class="tq-card__head">
        <h2 class="tq-card__title"><?php echo t('تدريب مركز'); ?></h2>
        <span class="tq-caption" data-tq-mk-counter></span>
      </div>

      <div data-tq-mk-progress style="margin-block-end:var(--tq-space-l)"></div>

      <span class="tq-eyebrow" data-tq-mk-source></span>

      <form data-tq-mk-form style="margin-block-start:var(--tq-space-m)">
        <fieldset style="border:0;padding:0;margin:0">
          <legend class="tq-h2" style="margin-block-end:var(--tq-space-l)" data-tq-mk-q></legend>
          <div data-tq-mk-options></div>
        </fieldset>

        <p class="tq-field__msg" data-tq-mk-hint hidden style="color:var(--tq-danger)">
          <?php echo t('اختر إجابة قبل التحقق.'); ?>
        </p>

        <div class="tq-row" style="flex-wrap:wrap;margin-block-start:var(--tq-space-xl)">
          <button class="tq-btn tq-btn--primary" type="submit"><?php echo t('تحقق'); ?></button>
          <button class="tq-btn tq-btn--ghost" type="button" data-tq-mk-skip><?php echo t('تخط'); ?></button>
          <button class="tq-btn tq-btn--ghost" type="button" data-tq-mk-quit><?php echo t('أنه التدريب'); ?></button>
        </div>
      </form>
    </section>

    <!-- الحكم بعد كل إجابة. ولا يعطى الحل بحال — الاستدعاء هو التمرين -->
    <section class="tq-card" data-tq-mk-verdict hidden>
      <div class="tq-row" style="gap:var(--tq-space-l);align-items:flex-start">
        <span class="tq-icon-box" data-tq-mk-verdict-icon aria-hidden="true"></span>
        <div style="flex:1">
          <h2 class="tq-card__title" style="margin:0" data-tq-mk-verdict-title></h2>
          <p class="tq-body" style="margin:var(--tq-space-s) 0 var(--tq-space-l)" data-tq-mk-verdict-text></p>
          <div class="tq-row" style="flex-wrap:wrap" data-tq-mk-verdict-actions></div>
        </div>
      </div>
    </section>

    <!-- خلاصة الجلسة -->
    <section class="tq-card" data-tq-mk-done hidden>
      <div class="tq-empty">
        <span class="tq-s-art tq-pastel tq-pastel--mint" aria-hidden="true">
          <span class="tq-pastel__icon"><?php echo tq_icon('check-badge', 34); ?></span>
        </span>
        <h3 class="tq-empty__title"><?php echo t('انتهى التدريب'); ?></h3>
        <p class="tq-empty__text" data-tq-mk-done-text></p>
        <div class="tq-row" style="flex-wrap:wrap;justify-content:center">
          <button class="tq-btn tq-btn--secondary" type="button" data-tq-mk-back><?php echo t('عد إلى الدفتر'); ?></button>
          <a class="tq-btn tq-btn--primary" href="<?php echo base_url('student/reviews'); ?>"><?php echo t('راجع المستحق اليوم'); ?></a>
        </div>
      </div>
    </section>

  </div>
</div>

<style>
.tq-mk-list { display: flex; flex-direction: column; gap: var(--tq-space-s); }

.tq-mk-row {
  display: flex; flex-wrap: wrap; gap: var(--tq-space-m);
  align-items: flex-start; padding: var(--tq-space-l);
  border: 1px solid var(--tq-line); border-radius: var(--tq-radius-small);
  background: var(--tq-surface);
}
.tq-mk-row__main { flex: 1; min-inline-size: 200px; display: flex; flex-direction: column; gap: 4px; }
.tq-mk-row__q { font-weight: 600; }
.tq-mk-row__where { font-size: .84rem; color: var(--tq-text3); }
.tq-mk-row__side { display: flex; flex-wrap: wrap; gap: var(--tq-space-s); align-items: center; }

/* عدد التكرار: الرقم وحده لا يقول شيئا، فالشارة تقول «مرات» وتلون
   بالتدرج — مرة واحدة ليست كخمس. */
.tq-mk-count {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 3px 10px; border-radius: var(--tq-radius-pill);
  font-size: .78rem; font-weight: 700;
  background: var(--tq-peach-fill); color: var(--tq-peach-ink);
}
.tq-mk-count--hot { background: var(--tq-rose-fill); color: var(--tq-rose-ink); }

.tq-mk-due {
  font-size: .78rem; color: var(--tq-text3);
  padding: 3px 10px; border-radius: var(--tq-radius-pill);
  background: var(--tq-navyWash);
}

/* الخيار في التدريب: الصنف نفسه الذي تستعمله شاشة المراجعة، فلا يتعلم
   الطالب شكلين لفعل واحد. */
.tq-mk-opt {
  display: block; padding: var(--tq-space-m) var(--tq-space-l);
  border: 1px solid var(--tq-line); border-radius: var(--tq-radius-small);
  background: var(--tq-surface); cursor: pointer; margin-block-end: var(--tq-space-s);
  transition: border-color 140ms, background 140ms;
}
.tq-mk-opt:hover { border-color: var(--tq-tealLift); }
.tq-mk-opt input { margin-inline-end: var(--tq-space-s); }
.tq-mk-opt:has(input:checked) { border-color: var(--tq-teal); background: var(--tq-mint-fill); }
.tq-mk-opt.is-on { border-color: var(--tq-teal); background: var(--tq-mint-fill); }
.tq-mk-opt:has(input:focus-visible) { outline: 2px solid var(--tq-focusRing); outline-offset: 2px; }
</style>

<script>
/**
 * دفتر الأخطاء — كل حكم من الخادم.
 *
 * الصفحة لا تحمل إجابة صحيحة واحدة، ولا تحسب فاصلا، ولا تقرر «أتقن».
 * ترسل ما اختاره الطالب وتعرض ما يرد. وهذا ليس حرصا زائدا: الدفتر يغذي
 * `skill_state` و`review_queue`، فحساب أي منهما هنا يجعل خريطة الإتقان
 * رقما يكتبه المتصفح لا حالة تقاس.
 */
(function () {
  var root = document.querySelector('[data-tq-mistakes]');
  if (!root) return;

  var GATE = root.getAttribute('data-tq-gate');
  var LESSON = root.getAttribute('data-tq-lesson-base');

  var $ = function (sel) { return root.querySelector(sel); };
  var show = function (el, on) { if (el) el.hidden = !on; };

  var el = {
    skeleton: $('[data-tq-mk-skeleton]'),
    error:    $('[data-tq-mk-error]'),
    errorMsg: $('[data-tq-mk-error-msg]'),
    body:     $('[data-tq-mk-body]'),
    empty:    $('[data-tq-mk-empty]'),
    has:      $('[data-tq-mk-has]'),
    stats:    $('[data-tq-mk-stats]'),
    listCard: $('[data-tq-mk-list-card]'),
    list:     $('[data-tq-mk-list]'),
    filter:   $('[data-tq-mk-filter]'),
    fEmpty:   $('[data-tq-mk-filtered-empty]'),
    drill:    $('[data-tq-mk-drill]'),
    counter:  $('[data-tq-mk-counter]'),
    progress: $('[data-tq-mk-progress]'),
    source:   $('[data-tq-mk-source]'),
    form:     $('[data-tq-mk-form]'),
    q:        $('[data-tq-mk-q]'),
    options:  $('[data-tq-mk-options]'),
    hint:     $('[data-tq-mk-hint]'),
    verdict:  $('[data-tq-mk-verdict]'),
    vIcon:    $('[data-tq-mk-verdict-icon]'),
    vTitle:   $('[data-tq-mk-verdict-title]'),
    vText:    $('[data-tq-mk-verdict-text]'),
    vActions: $('[data-tq-mk-verdict-actions]'),
    done:     $('[data-tq-mk-done]'),
    doneText: $('[data-tq-mk-done-text]')
  };

  var state = { all: [], queue: [], idx: 0, right: 0, wrong: 0 };

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /** رمز الجلسة — يطبعه `portal_open.php` في `<meta name="tq-csrf">`. */
  function tqCsrf() {
    var m = document.querySelector('meta[name="tq-csrf"]');
    return m ? m.getAttribute('content') || '' : '';
  }

  function api(path, opts) {
    opts = opts || {};
    /* TQ-GATE-CSRF — الرمز في الترويسة: `$_POST` فارغ مع جسم JSON،
       فكان كل نداء كتابة يرد 403 قبل أن يبلغ المتحكم. */
    var h = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
    if (opts.method && opts.method !== 'GET') h['X-CSRF-Token'] = tqCsrf();
    /* TQ-RAW-ERROR — الغلاف الواحد في `tq-i18n.js`: هو الذي يفرق بين
       شبكة مقطوعة وجلسة منتهية وخادم متعثر، ويخرج عربية جاهزة للعرض.
       وكانت هذه الكتلة تعالج خطأ الخادم وحده — وهو الطريق الوحيد الذي
       يرد نصا عربيا أصلا — فيبقى «Failed to fetch» يكتب حرفا في لوح
       الخطأ أمام طالب لا يقرأ الإنجليزية ولا يعرف أن يفحص اتصاله. */
    return TQ.gateFetch(GATE + '/' + path, {
      method: opts.method || 'GET',
      credentials: 'same-origin',
      headers: h,
      body: opts.body ? JSON.stringify(opts.body) : undefined
    });
  }

  /* ---- الدفتر ---------------------------------------------------- */

  function load() {
    show(el.skeleton, true); show(el.error, false); show(el.body, false);

    api('mistakes').then(function (d) {
      state.all = (d && d.mistakes) || [];
      show(el.skeleton, false);
      show(el.body, true);

      if (!state.all.length) { show(el.empty, true); show(el.has, false); return; }

      show(el.empty, false); show(el.has, true);
      buildFilter();
      renderStats();
      renderList();
    }).catch(function (e) {
      show(el.skeleton, false);
      show(el.error, true);
      if (el.errorMsg) el.errorMsg.textContent = e.message;
    });
  }

  function buildFilter() {
    var seen = {}, opts = '';
    state.all.forEach(function (m) {
      if (m.course_title && !seen[m.course_title]) {
        seen[m.course_title] = 1;
        opts += '<option value="' + esc(m.course_title) + '">' + esc(m.course_title) + '</option>';
      }
    });
    if (el.filter) el.filter.insertAdjacentHTML('beforeend', opts);
  }

  function visible() {
    var f = el.filter ? el.filter.value : '';
    return f ? state.all.filter(function (m) { return m.course_title === f; }) : state.all;
  }

  function renderStats() {
    if (!el.stats) return;
    var rows = state.all;
    var total = rows.reduce(function (s, m) { return s + (m.wrong_count || 0); }, 0);
    var concepts = {}; rows.forEach(function (m) { if (m.objective_id) concepts[m.objective_id] = 1; });
    var scheduled = rows.filter(function (m) { return m.due_at; }).length;

    el.stats.innerHTML =
      stat(rows.length, 'سؤالا في دفترك', 'help', 'peach') +
      stat(total, 'مرة أخطأت فيها', 'refresh', 'rose') +
      stat(Object.keys(concepts).length, 'مفهوما يحتاج تثبيتا', 'target', 'sky') +
      stat(scheduled, 'منها مجدول للمراجعة', 'calendar', 'mint');
  }

  function stat(v, label, icon, pastel) {
    return '<div class="tq-s-stat tq-pastel tq-pastel--' + pastel + '">' +
           '<span class="tq-s-stat__value tq-pastel__title">' + v + '</span>' +
           '<span class="tq-s-stat__label tq-pastel__body">' + esc(label) + '</span></div>';
  }

  function renderList() {
    var rows = visible();
    show(el.fEmpty, rows.length === 0);

    el.list.innerHTML = rows.map(function (m) {
      var where = [m.course_title, m.lesson_title, m.objective_text]
        .filter(Boolean).map(esc).join(' · ');

      var hot = (m.wrong_count || 0) >= 3 ? ' tq-mk-count--hot' : '';
      var side = '<span class="tq-mk-count' + hot + '">' +
                 (m.wrong_count || 1) + ' مرات</span>';

      if (m.due_at) {
        side += '<span class="tq-mk-due">مجدول للمراجعة</span>';
      }
      /* الرابط إلى ثانية الشرح لا إلى أول الدرس: الخطأ في مفهوم، وللمفهوم
         موضع في الفيديو — وإرساله إلى الدقيقة صفر يجعله يبحث عما أخطأ فيه. */
      if (m.lesson_id && m.course_id) {
        side += '<a class="tq-btn tq-btn--ghost tq-btn--sm" href="' +
                LESSON + '/' + m.course_id + '/' + m.lesson_id +
                (m.at_second ? '?t=' + m.at_second : '') + '">راجع الشرح</a>';
      }

      return '<div class="tq-mk-row">' +
               '<div class="tq-mk-row__main">' +
                 '<span class="tq-mk-row__q">' + esc(m.title) + '</span>' +
                 (where ? '<span class="tq-mk-row__where">' + where + '</span>' : '') +
               '</div>' +
               '<div class="tq-mk-row__side">' + side + '</div>' +
             '</div>';
    }).join('');
  }

  if (el.filter) el.filter.addEventListener('change', function () { renderStats(); renderList(); });

  /* ---- التدريب المركز -------------------------------------------- */

  function startDrill() {
    var f = el.filter ? el.filter.value : '';
    show(el.listCard, false);
    show(el.stats, false);
    show(el.drill, true);
    el.drill.innerHTML = el.drill.innerHTML; // لا شيء — يبقى المحتوى كما هو

    api('practice_questions?limit=10').then(function (d) {
      var qs = (d && d.questions) || [];
      if (f) qs = qs.filter(function (q) { return q.course_title === f; });

      if (!qs.length) {
        show(el.drill, false);
        show(el.listCard, true);
        show(el.stats, true);
        return;
      }
      state.queue = qs; state.idx = 0; state.right = 0; state.wrong = 0;
      renderQuestion();
    }).catch(function (e) {
      show(el.drill, false);
      show(el.error, true);
      if (el.errorMsg) el.errorMsg.textContent = e.message;
    });
  }

  function renderQuestion() {
    var q = state.queue[state.idx];
    if (!q) return finish();

    show(el.drill, true); show(el.verdict, false); show(el.done, false);
    show(el.hint, false);

    el.counter.textContent = 'سؤال ' + (state.idx + 1) + ' من ' + state.queue.length;
    el.source.textContent = [q.course_title, q.lesson_title].filter(Boolean).join(' · ');
    el.q.textContent = q.title;

    var pct = Math.round((state.idx / state.queue.length) * 100);
    el.progress.innerHTML =
      '<div class="tq-progress" role="progressbar" aria-valuenow="' + pct +
      '" aria-valuemin="0" aria-valuemax="100">' +
      '<div class="tq-progress__track"><div class="tq-progress__fill" style="inline-size:' + pct + '%"></div></div>' +
      '<span class="tq-progress__value">' + pct + '%</span></div>';

    var opts = Array.isArray(q.options) ? q.options : [];
    el.options.innerHTML = opts.map(function (o, i) {
      var v = (o && typeof o === 'object') ? (o.text || o.title || '') : o;
      return '<label class="tq-mk-opt"><input type="radio" name="mkopt" value="' + esc(v) + '">' +
             '<span>' + esc(v) + '</span></label>';
    }).join('');
  }

  el.options.addEventListener('change', function (e) {
    var boxes = el.options.querySelectorAll('.tq-mk-opt');
    for (var i = 0; i < boxes.length; i++) {
      boxes[i].classList.toggle('is-on', boxes[i].querySelector('input').checked);
    }
  });

  el.form.addEventListener('submit', function (e) {
    e.preventDefault();
    var picked = el.options.querySelector('input[name="mkopt"]:checked');
    if (!picked) { show(el.hint, true); return; }
    show(el.hint, false);

    var q = state.queue[state.idx];
    var btn = el.form.querySelector('button[type="submit"]');
    btn.disabled = true;

    api('practice_answer', { method: 'POST', body: { question_id: q.id, given: [picked.value] } })
      .then(function (r) {
        btn.disabled = false;
        if (r.correct) state.right++; else state.wrong++;
        verdict(r, q);
      })
      .catch(function (err) {
        btn.disabled = false;
        show(el.error, true);
        if (el.errorMsg) el.errorMsg.textContent = err.message;
      });
  });

  function verdict(r, q) {
    show(el.drill, false);
    show(el.verdict, true);

    var ok = !!r.correct;
    el.vIcon.className = 'tq-icon-box ' + (ok ? 'tq-pastel--mint' : 'tq-pastel--peach');
    el.vTitle.textContent = ok ? 'صحيحة' : 'ما زالت تحتاج تثبيتا';

    /* ولا يعطى الحل: الاستدعاء هو التمرين، وإعطاء الجواب هنا يحول
       التدريب إلى قراءة. ويقال له أين يراجع بدلا من ذلك. */
    var txt;
    if (ok) {
      txt = r.interval_days
        ? 'أحسنت. باعدنا موعد هذا السؤال إلى ' + r.interval_days + ' يوما.'
        : 'أحسنت. سجل هذا في مستوى مهارتك.';
    } else {
      txt = 'لا بأس — هذا موضع التدريب. راجع شرح المفهوم ثم عد إليه.';
    }
    el.vText.textContent = txt;

    var acts = '<button class="tq-btn tq-btn--primary" type="button" data-tq-mk-next>السؤال التالي</button>';
    if (!ok && q.lesson_id && q.course_id) {
      acts += '<a class="tq-btn tq-btn--secondary" href="' + LESSON + '/' + q.course_id + '/' + q.lesson_id +
              (q.at_second ? '?t=' + q.at_second : '') + '">راجع الشرح</a>';
    }
    el.vActions.innerHTML = acts;
  }

  root.addEventListener('click', function (e) {
    if (e.target.closest('[data-tq-mk-start]')) { startDrill(); }
    else if (e.target.closest('[data-tq-mk-next]')) { state.idx++; renderQuestion(); }
    else if (e.target.closest('[data-tq-mk-skip]')) { state.idx++; renderQuestion(); }
    else if (e.target.closest('[data-tq-mk-quit]') || e.target.closest('[data-tq-mk-back]')) { backToBook(); }
    else if (e.target.closest('[data-tq-mk-retry]')) { load(); }
  });

  function finish() {
    show(el.drill, false); show(el.verdict, false); show(el.done, true);
    el.doneText.textContent = 'أجبت ' + state.right + ' صحيحة و' + state.wrong +
      ' تحتاج تثبيتا. وما أخطأته يبقى في دفترك حتى تتقنه.';
  }

  function backToBook() {
    show(el.drill, false); show(el.verdict, false); show(el.done, false);
    show(el.listCard, true); show(el.stats, true);
    load();   // الأرقام تغيرت بعد التدريب، فتقرأ من جديد لا تحسب هنا
  }

  load();
})();
</script>

<?php include 'portal_close.php'; ?>
