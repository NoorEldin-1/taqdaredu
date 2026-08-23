/* منصة تقدر — شاشة المراجعة المتباعدة.
 *
 * الجدولة كانت تعمل ولا ترى: كل تسليم مراجعة يكتب في `review_queue`، ولا
 * عميل ينادي `taqdar_gate/reviews`. هذا الملف هو النداء الغائب.
 *
 * وكما في مشغل الدرس: لا تصل الإجابات الصحيحة إلى المتصفح أبدا. ترسل
 * إجابة الطالب ويعود الحكم ومعه الفاصل الجديد وموعد العودة — كلاهما محسوب
 * في الخادم. فما هنا عرض لقرار لا اتخاذ له.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-tq-reviews]');
  if (!root) return;

  var GATE = root.getAttribute('data-tq-gate');
  var LESSONS = root.getAttribute('data-tq-lessons') || '';
  var $ = function (s, r) { return (r || root).querySelector(s); };
  var LRI = '⁦', PDI = '⁩';

  var state = {
    queue: [], index: 0,
    answered: 0, correct: 0,
    total_due: 0, batch: 0, remaining: 0,
    busy: false
  };

  /* ---- نداء الخادم: مغلف موحد، والرسالة العربية تأتي منه لا نخترعها ---- */
  /** رمز الجلسة — يطبعه `portal_open.php` في `<meta name="tq-csrf">`. */
  function tqCsrf() {
    var m = document.querySelector('meta[name="tq-csrf"]');
    return m ? m.getAttribute('content') || '' : '';
  }

  function call(path, body) {
    var opt = { credentials: 'same-origin', headers: { 'Accept': 'application/json' } };
    if (body) {
      opt.method = 'POST';
      opt.headers['Content-Type'] = 'application/json';
      /* TQ-GATE-CSRF — الرمز في الترويسة لا في الجسم.
         `CI_Security::csrf_verify()` يبحث عنه في `$_POST`، وجسم JSON
         يترك `$_POST` فارغا — فكان كل POST إلى البوابة يرد 403 قبل أن
         يبلغ المتحكم: التقدم، وبدء المراجعة، وتسليمها، والملاحظات.
         والبوابة تفحصه بنفسها من هنا (`Taqdar_gate::csrf_ok`). */
      opt.headers['X-CSRF-Token'] = tqCsrf();
      opt.body = JSON.stringify(body);
    }
    return fetch(GATE + '/' + path, opt).then(function (r) {
      return r.json().then(function (j) {
        if (!r.ok || (j && j.error)) {
          var e = (j && j.error) || {};
          throw {
            code: e.code || 'HTTP_' + r.status,
            message: e.message_ar || 'تعذر إتمام الطلب',
            details: e.details || {}
          };
        }
        return j.data !== undefined ? j.data : j;
      });
    });
  }

  function show(sel, on) { var el = $(sel); if (el) el.hidden = !on; }
  function text(sel, v) { var el = $(sel); if (el) el.textContent = v == null ? '' : String(v); }
  function iso(n) { return LRI + n + PDI; }
  function int(v) { return parseInt(v, 10) || 0; }

  /** المثنى والجمع في العربية ليسا s تضاف — «بعد 2 يوم» خطأ لغوي ظاهر. */
  function whenBack(n) {
    n = int(n);
    if (n <= 0) return 'اليوم نفسه';
    if (n === 1) return 'غدا';
    if (n === 2) return 'بعد يومين';
    if (n <= 10) return 'بعد ' + iso(n) + ' أيام';
    return 'بعد ' + iso(n) + ' يوما';
  }

  function questions(n) {
    n = int(n);
    if (n === 1) return 'سؤال واحد';
    if (n === 2) return 'سؤالان';
    if (n <= 10) return iso(n) + ' أسئلة';
    return iso(n) + ' سؤالا';
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /** رابط الدرس الذي جاء منه السؤال — يشتق من رابط «دروسي» لا يلفق. */
  function lessonHref(q) {
    if (!q || !q.lesson_id || !LESSONS) return '';
    return LESSONS.replace(/\/lessons\/?$/, '/lesson/' + int(q.course_id) + '/' + int(q.lesson_id));
  }

  /* ---- تحميل الدفعة ---- */
  function load() {
    show('[data-tq-rv-skeleton]', true);
    show('[data-tq-rv-body]', false);
    show('[data-tq-rv-error]', false);

    call('reviews').then(function (d) {
      show('[data-tq-rv-skeleton]', false);
      show('[data-tq-rv-body]', true);

      state.queue = (d.due || []).filter(function (q) { return q && q.question_id; });
      state.index = 0;
      state.answered = 0;
      state.correct = 0;
      state.total_due = int(d.total_due);
      state.remaining = state.total_due;

      /* «دفعة اليوم» هي ما حمل فعلا الآن، لا سقف الإعداد.
         عرض `daily_batch` كان يكتب «10» بجوار «0 سؤالا مستحقا»
         فيقرأها الطالب عشرة أسئلة تنتظره وليس أمامه سؤال واحد. */
      state.batch = int(d.count);
      if (!state.batch) state.batch = state.queue.length;

      text('[data-tq-rv-total]', iso(state.total_due));
      text('[data-tq-rv-batch]', iso(state.batch));

      show('[data-tq-rv-verdict]', false);
      show('[data-tq-rv-done]', false);

      if (!state.queue.length) {
        show('[data-tq-rv-question]', false);
        show('[data-tq-rv-empty]', true);
        return;
      }
      show('[data-tq-rv-empty]', false);
      renderQuestion();
    }).catch(function (e) {
      show('[data-tq-rv-skeleton]', false);
      show('[data-tq-rv-body]', false);
      text('[data-tq-rv-error-msg]', e.message);
      show('[data-tq-rv-error]', true);
    });
  }

  /* ---- عرض سؤال واحد ---- */
  function renderQuestion() {
    var q = state.queue[state.index];
    if (!q) return finish();

    show('[data-tq-rv-verdict]', false);
    show('[data-tq-rv-done]', false);
    show('[data-tq-rv-empty]', false);
    show('[data-tq-rv-hint]', false);

    text('[data-tq-rv-counter]', 'السؤال ' + iso(state.index + 1) + ' من ' + iso(state.queue.length));

    var pct = Math.round(state.index / state.queue.length * 100);
    var pw = $('[data-tq-rv-progress]');
    if (pw) {
      pw.innerHTML = '<div class="tq-progress" role="progressbar" aria-valuenow="' + pct + '"'
        + ' aria-valuemin="0" aria-valuemax="100" aria-label="تقدم جلسة المراجعة">'
        + '<div class="tq-progress__track"><div class="tq-progress__fill" style="inline-size:' + pct + '%"></div></div>'
        + '<span class="tq-progress__value">' + iso(pct + '%') + '</span></div>';
    }

    var src = [];
    if (q.course_title) src.push(q.course_title);
    if (q.lesson_title) src.push(q.lesson_title);
    if (q.objective_text) src.push(q.objective_text);
    text('[data-tq-rv-source]', src.join(' · '));

    text('[data-tq-rv-title]', q.title || '');

    var box = $('[data-tq-rv-options]');
    if (box) box.innerHTML = optionsHtml(q);

    var btn = $('[data-tq-rv-submit]');
    if (btn) btn.removeAttribute('data-loading');

    show('[data-tq-rv-question]', true);

    var first = root.querySelector('[data-tq-rv-options] input');
    if (first) first.focus({ preventScroll: true });
  }

  /**
   * الخيارات. الاختيار المتعدد مربعات لا دوائر — والدائرة تعد بإجابة
   * واحدة، فلو استعملت في سؤال متعدد لأخطأ الطالب في القراءة لا في المعرفة.
   * والسؤال بلا خيارات (ملء الفراغ) حقل نص.
   */
  function optionsHtml(q) {
    var opts = (q.options || []).filter(function (o) { return o !== null && o !== ''; });

    if (!opts.length) {
      return '<label class="tq-field" for="tq-rv-typed">'
        + '<span class="tq-field__label">اكتب إجابتك</span>'
        + '<input class="tq-input" id="tq-rv-typed" type="text" autocomplete="off"'
        + ' spellcheck="false" data-tq-rv-input></label>';
    }

    var multi = (q.type === 'multiple_choice');
    var kind = multi ? 'checkbox' : 'radio';
    var head = multi
      ? '<p class="tq-caption" style="margin-block-end:var(--tq-space-m)">اختر كل الإجابات الصحيحة.</p>'
      : '';

    return head + opts.map(function (o, j) {
      var id = 'tq-rv-o' + j;
      return '<label class="tq-row" for="' + id + '" style="gap:var(--tq-space-s);cursor:pointer;'
        + 'align-items:flex-start;margin-block-end:var(--tq-space-m)">'
        + '<input type="' + kind + '" id="' + id + '" name="tqrv" value="' + escapeHtml(o) + '">'
        + '<span class="tq-body">' + escapeHtml(o) + '</span></label>';
    }).join('');
  }

  /** إجابة الطالب كما هي — التصحيح ليس من شأن هذه الشاشة. */
  function given(q) {
    var form = $('[data-tq-rv-form]');
    if (!form) return [];

    if (!(q.options || []).length) {
      var t = form.querySelector('[data-tq-rv-input]');
      var v = t ? t.value.trim() : '';
      return v === '' ? [] : [v];
    }
    var sel = form.querySelectorAll('input[name="tqrv"]:checked');
    return Array.prototype.map.call(sel, function (i) { return i.value; });
  }

  var form = $('[data-tq-rv-form]');
  if (form) form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    if (state.busy) return;

    var q = state.queue[state.index];
    if (!q) return;

    var answer = given(q);
    if (!answer.length) {
      show('[data-tq-rv-hint]', true);
      return;
    }
    show('[data-tq-rv-hint]', false);

    var btn = $('[data-tq-rv-submit]');
    state.busy = true;
    if (btn) btn.setAttribute('data-loading', 'true');

    call('review_answer', { question_id: q.question_id, given: answer }).then(function (r) {
      state.busy = false;
      if (btn) btn.removeAttribute('data-loading');
      state.answered++;
      if (r.correct) state.correct++;
      state.remaining = int(r.remaining_due);
      text('[data-tq-rv-total]', iso(state.remaining));
      show('[data-tq-rv-question]', false);
      renderVerdict(r, q);
    }).catch(function (e) {
      state.busy = false;
      if (btn) btn.removeAttribute('data-loading');
      failure(e.message);
    });
  });

  /**
   * الحكم. الخطأ لا يعطى معه الحل أبدا — يقال متى يعود السؤال، ويشار
   * إلى موضع شرحه في الدرس. إعطاء الإجابة هنا يقتل قياس الإتقان في المرة
   * القادمة: يحفظها الطالب ولا يستدعيها.
   */
  function renderVerdict(r, q) {
    var icon = $('[data-tq-rv-verdict-icon]');
    var acts = $('[data-tq-rv-verdict-actions]');
    var back = whenBack(r.interval_days);

    if (icon) {
      icon.className = 'tq-icon-box ' + (r.correct ? 'tq-pastel--mint' : 'tq-pastel--peach');
      icon.innerHTML = r.correct
        ? '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>'
        : '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16v.01"/></svg>';
    }

    if (r.correct) {
      text('[data-tq-rv-verdict-title]', 'إجابة صحيحة');
      text('[data-tq-rv-verdict-text]', 'ثبت هذا المفهوم أكثر، فتباعد موعده: يعود إليك ' + back + '.');
    } else {
      text('[data-tq-rv-verdict-title]', 'ليست الإجابة الصحيحة');
      text('[data-tq-rv-verdict-text]',
        'لن نعطيك الحل — يعود السؤال ' + back + ' لتجيبه بنفسك. وإن أردت أن تراجع شرحه فهو في درسه.');
    }

    if (acts) {
      var html = '';
      var last = (state.index >= state.queue.length - 1);
      html += '<button class="tq-btn tq-btn--primary" type="button" data-tq-rv-next>'
        + (last ? 'أنه الجلسة' : 'السؤال التالي') + '</button>';

      var href = lessonHref(q);
      if (!r.correct && href) {
        html += '<a class="tq-btn tq-btn--secondary" href="' + escapeHtml(href) + '">راجع الدرس</a>';
      }
      acts.innerHTML = html;
    }

    show('[data-tq-rv-verdict]', true);
    var v = $('[data-tq-rv-verdict]');
    if (v && v.scrollIntoView) v.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  /* ---- خلاصة الجلسة ---- */
  function finish() {
    show('[data-tq-rv-question]', false);
    show('[data-tq-rv-verdict]', false);

    text('[data-tq-rv-done-answered]', iso(state.answered));
    text('[data-tq-rv-done-correct]', iso(state.correct));
    text('[data-tq-rv-done-remaining]', iso(state.remaining));

    text('[data-tq-rv-done-text]', state.remaining > 0
      ? 'ما زال لديك ' + questions(state.remaining) + ' مستحقا اليوم — تعرض في دفعة تالية.'
      : 'أنهيت كل ما استحق اليوم. عد غدا لما يحل موعده.');

    var acts = $('[data-tq-rv-done-actions]');
    if (acts) {
      acts.innerHTML = (state.remaining > 0
        ? '<button class="tq-btn tq-btn--primary" type="button" data-tq-rv-more>تابع الدفعة التالية</button>'
        : '')
        + '<a class="tq-btn tq-btn--secondary" href="' + escapeHtml(LESSONS) + '">عد إلى دروسك</a>';
    }

    show('[data-tq-rv-done]', true);
    var d = $('[data-tq-rv-done]');
    if (d && d.scrollIntoView) d.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function failure(msg) {
    text('[data-tq-rv-error-msg]', msg);
    show('[data-tq-rv-error]', true);
    var e = $('[data-tq-rv-error]');
    if (e && e.scrollIntoView) e.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(function () { show('[data-tq-rv-error]', false); }, 8000);
  }

  /* ---- الأزرار ---- */
  root.addEventListener('click', function (ev) {
    if (ev.target.closest('[data-tq-rv-next]')) {
      state.index++;
      if (state.index >= state.queue.length) finish();
      else renderQuestion();
      return;
    }
    if (ev.target.closest('[data-tq-rv-skip]')) {
      // التخطي محلي بحت: لا يرسل شيء، فالسؤال يبقى مستحقا كما هو.
      state.index++;
      if (state.index >= state.queue.length) finish();
      else renderQuestion();
      return;
    }
    if (ev.target.closest('[data-tq-rv-more]') || ev.target.closest('[data-tq-rv-retry]')) {
      load();
    }
  });

  load();
})();
