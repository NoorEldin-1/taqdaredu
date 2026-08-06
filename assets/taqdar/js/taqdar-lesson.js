/* منصة تقدر — مشغل الدرس وبوابة الإتقان.
 *
 * هذا الملف هو الجسر الذي كان غائبا: الشاشة لا تستعلم من قاعدة البيانات،
 * بل تكلم `taqdar_gate` وحده. والقفل وقرار البوابة يحسمان في الخادم —
 * وما هنا عرض لقراره لا اتخاذ له. ولذلك لا تصل الإجابات الصحيحة إلى
 * المتصفح أبدا: ترسل إجابة الطالب ويعود الحكم.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-tq-lesson]');
  if (!root) return;

  var GATE = root.getAttribute('data-tq-gate');
  var LESSON = parseInt(root.getAttribute('data-tq-lesson'), 10) || 0;
  var $ = function (s, r) { return (r || root).querySelector(s); };
  var LRI = '⁦', PDI = '⁩';

  var state = { lesson: null, attempt: null, questions: [], watched: 0, position: 0, ticker: null };

  /* ---- نداء الخادم: مغلف موحد، والرسالة العربية تأتي منه لا نخترعها ---- */
  function call(path, body) {
    var opt = { credentials: 'same-origin', headers: { 'Accept': 'application/json' } };
    if (body) {
      opt.method = 'POST';
      opt.headers['Content-Type'] = 'application/json';
      opt.body = JSON.stringify(body);
    }
    return fetch(GATE + '/' + path, opt).then(function (r) {
      return r.json().then(function (j) {
        if (!r.ok || (j && j.error)) {
          var e = (j && j.error) || {};
          throw { code: e.code || 'HTTP_' + r.status, message: e.message_ar || 'تعذر إتمام الطلب', details: e.details || {} };
        }
        return j.data !== undefined ? j.data : j;
      });
    });
  }

  function show(sel, on) { var el = $(sel); if (el) el.hidden = !on; }
  function text(sel, v) { var el = $(sel); if (el) el.textContent = v == null ? '' : String(v); }
  function iso(n) { return LRI + n + PDI; }

  function mmss(sec) {
    sec = Math.max(0, parseInt(sec, 10) || 0);
    var m = Math.floor(sec / 60), s = sec % 60;
    return iso(m + ':' + (s < 10 ? '0' : '') + s);
  }

  /* ---- تحميل الدرس ---- */
  function load() {
    show('[data-tq-lesson-skeleton]', true);
    show('[data-tq-lesson-body]', false);
    show('[data-tq-lesson-locked]', false);
    show('[data-tq-lesson-error]', false);

    call('lesson/' + LESSON).then(render).catch(function (e) {
      show('[data-tq-lesson-skeleton]', false);
      if (e.code === 'MASTERY_LOCKED' || e.code === 'NOT_ENTITLED') {
        show('[data-tq-lesson-locked]', true);
        text('[data-tq-locked-msg]', e.message);
        var b = e.details && e.details.blocking_lesson_id;
        var back = $('[data-tq-locked-back]');
        if (b && back) { back.href = back.href.replace(/\/lessons.*$/, '/lesson/0/' + b); back.textContent = 'اذهب إلى الدرس المطلوب'; }
        return;
      }
      show('[data-tq-lesson-error]', true);
      text('[data-tq-error-msg]', e.message);
    });
  }

  function render(d) {
    state.lesson = d;
    show('[data-tq-lesson-skeleton]', false);
    show('[data-tq-lesson-body]', true);

    var L = d.lesson || {};
    document.title = (L.title || 'الدرس') + ' | تقدر';
    text('[data-tq-lesson-title]', L.title || '');
    text('[data-tq-lesson-course]', L.course_title || '');
    text('[data-tq-lesson-duration]', L.duration ? iso(L.duration) : '');
    text('[data-tq-lesson-summary]', L.summary || '');

    var p = d.progress || {};
    state.position = parseInt(p.position_sec, 10) || 0;
    var pct = L.duration_sec ? Math.min(100, Math.round((p.watch_seconds || 0) / L.duration_sec * 100)) : 0;
    var pw = $('[data-tq-lesson-progress]');
    if (pw) {
      pw.innerHTML = '<div class="tq-progress"><div class="tq-progress__track">'
        + '<div class="tq-progress__fill" style="inline-size:' + pct + '%"></div></div>'
        + '<span class="tq-progress__value">' + iso(pct + '%') + '</span></div>';
    }

    var badge = $('[data-tq-lesson-badge]');
    if (badge) {
      badge.innerHTML = p.mastered_at
        ? '<span class="tq-badge tq-badge--mastered">متقن</span>'
        : (p.completed_at ? '<span class="tq-badge tq-badge--progress">شوهد</span>'
                          : '<span class="tq-badge tq-badge--idle">لم يبدأ</span>');
    }

    mountPlayer(d.playback || {}, L);
    mountObjectives(d.objectives || []);
    mountAttachments(L.attachment);
    mountNav(d);

    // المراجعة تظهر حين يوجد تقييم مرتبط بالدرس وحين لم يتقن بعد
    show('[data-tq-gate-intro]', !!d.review && !p.mastered_at);
  }

  function mountPlayer(pb, L) {
    var frame = $('[data-tq-player-frame]');
    if (!frame) return;
    var url = pb.video_url || '';
    var t = (pb.video_type || '').toLowerCase();

    if (!url) {
      frame.innerHTML = '<div class="tq-empty"><p class="tq-empty__text">لا يوجد مقطع لهذا الدرس.</p></div>';
      return;
    }
    if (t === 'youtube' || /youtu\.?be/.test(url)) {
      var id = (url.match(/(?:v=|be\/|embed\/)([\w-]{6,})/) || [])[1] || '';
      frame.innerHTML = '<iframe src="https://www.youtube-nocookie.com/embed/' + id
        + '?rel=0&modestbranding=1" title="' + (L.title || '') + '" allowfullscreen loading="lazy"></iframe>';
      return;
    }
    if (t === 'vimeo' || /vimeo/.test(url)) {
      var vid = (url.match(/vimeo\.com\/(\d+)/) || [])[1] || '';
      frame.innerHTML = '<iframe src="https://player.vimeo.com/video/' + vid + '" allowfullscreen loading="lazy"></iframe>';
      return;
    }
    frame.innerHTML = '<video controls preload="metadata" playsinline></video>';
    var v = frame.querySelector('video');
    v.src = url;
    if (state.position > 0) v.currentTime = state.position;
    v.addEventListener('timeupdate', function () { state.position = Math.floor(v.currentTime); });
    v.addEventListener('play', startTicker);
    v.addEventListener('pause', stopTicker);
    v.addEventListener('ended', function () { stopTicker(); flush(true); });
  }

  /* ---- التقدم: يرسل كل 15 ثانية مشاهدة فعلية، لا كل نبضة ---- */
  function startTicker() {
    if (state.ticker) return;
    state.ticker = setInterval(function () { state.watched += 5; if (state.watched >= 15) flush(false); }, 5000);
  }
  function stopTicker() { if (state.ticker) { clearInterval(state.ticker); state.ticker = null; } flush(false); }

  function flush(ended) {
    if (!state.watched && !ended) return;
    var delta = state.watched; state.watched = 0;
    call('progress', { lesson_id: LESSON, position_sec: state.position, watched_delta: delta })
      .then(function () { if (ended) show('[data-tq-gate-intro]', !!(state.lesson && state.lesson.review)); })
      .catch(function () { /* التقدم لا يوقف المشاهدة؛ تعاد المحاولة في النبضة التالية */ });
  }
  addEventListener('beforeunload', function () { if (state.watched) flush(false); });

  function mountObjectives(list) {
    var box = $('[data-tq-objectives]');
    if (!box) return;
    if (!list.length) {
      box.innerHTML = '<li class="tq-caption tq-muted">لم تضف أهداف لهذا الدرس بعد.</li>';
      return;
    }
    box.innerHTML = list.map(function (o, i) {
      return '<li class="tq-s-row"><span class="tq-icon-box tq-pastel--sky" aria-hidden="true">'
        + '<span class="tq-num tq-num--sm">' + iso(i + 1) + '</span></span>'
        + '<span class="tq-caption">' + escapeHtml(o.text) + '</span></li>';
    }).join('');
  }

  function mountAttachments(att) {
    if (!att) return;
    var card = $('[data-tq-attachments-card]'), box = $('[data-tq-attachments]');
    if (!box) return;
    box.innerHTML = '<a class="tq-btn tq-btn--secondary tq-btn--block" href="' + escapeHtml(att) + '" download>تنزيل المرفق</a>';
    if (card) card.hidden = false;
  }

  function mountNav(d) {
    var base = root.getAttribute('data-tq-course');
    var prev = $('[data-tq-prev]'), next = $('[data-tq-next]'), lock = $('[data-tq-next-locked]');
    if (prev && d.prev_lesson_id) { prev.href = '../' + base + '/' + d.prev_lesson_id; prev.hidden = false; }
    if (!d.next_lesson_id) return;
    var mastered = d.progress && d.progress.mastered_at;
    if (mastered || !d.review) {
      if (next) { next.href = '../' + base + '/' + d.next_lesson_id; next.hidden = false; }
    } else if (lock) { lock.hidden = false; }
  }

  /* ---- بوابة الإتقان ---- */
  var startBtn = $('[data-tq-gate-start]');
  if (startBtn) startBtn.addEventListener('click', function () {
    startBtn.setAttribute('data-loading', 'true');
    call('review_start', { lesson_id: LESSON }).then(function (a) {
      startBtn.removeAttribute('data-loading');
      state.attempt = a.attempt_id;
      state.questions = a.questions || [];
      show('[data-tq-gate-intro]', false);
      show('[data-tq-gate-result]', false);
      renderQuestions();
      show('[data-tq-gate-quiz]', true);
      $('[data-tq-gate-quiz]').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }).catch(function (e) {
      startBtn.removeAttribute('data-loading');
      alertBox(e.message);
    });
  });

  function renderQuestions() {
    text('[data-tq-gate-counter]', state.questions.length + ' أسئلة');
    $('[data-tq-gate-counter]').textContent = iso(state.questions.length) + ' أسئلة';
    var box = $('[data-tq-gate-questions]');
    box.innerHTML = state.questions.map(function (q, i) {
      var opts = (q.options || []).map(function (o, j) {
        var id = 'q' + q.id + 'o' + j;
        return '<label class="tq-s-row" for="' + id + '" style="cursor:pointer">'
          + '<input type="radio" id="' + id + '" name="q' + q.id + '" value="' + escapeHtml(o) + '">'
          + '<span class="tq-body">' + escapeHtml(o) + '</span></label>';
      }).join('');
      return '<fieldset style="border:0;padding:0;margin:0 0 var(--tq-space-xl)">'
        + '<legend class="tq-strong" style="margin-block-end:var(--tq-space-m)">'
        + iso(i + 1) + '. ' + escapeHtml(q.title) + '</legend>' + opts + '</fieldset>';
    }).join('');
  }

  var form = $('[data-tq-gate-form]');
  if (form) form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var answers = state.questions.map(function (q) {
      var sel = form.querySelector('input[name="q' + q.id + '"]:checked');
      return { question_id: q.id, given: sel ? sel.value : '' };
    });
    if (answers.some(function (a) { return a.given === ''; })) {
      alertBox('أجب عن كل الأسئلة قبل التسليم.');
      return;
    }
    var btn = $('[data-tq-gate-submit]');
    btn.setAttribute('data-loading', 'true');
    call('review_submit', { attempt_id: state.attempt, answers: answers }).then(function (r) {
      btn.removeAttribute('data-loading');
      show('[data-tq-gate-quiz]', false);
      renderVerdict(r);
    }).catch(function (e) {
      btn.removeAttribute('data-loading');
      alertBox(e.message);
    });
  });

  /**
   * عرض قرار البوابة. الرسوب لا يعطي الإجابة أبدا — يتصاعد الدعم:
   * توقيت المفهوم الأضعف، ثم شرح بديل، ثم تمرير المفهوم إلى المعلم.
   */
  function renderVerdict(r) {
    var icon = $('[data-tq-result-icon]'), acts = $('[data-tq-result-actions]');
    var score = iso(r.score) + ' من ' + iso(r.out_of);

    if (r.passed) {
      icon.className = 'tq-icon-box tq-pastel--mint';
      icon.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>';
      text('[data-tq-result-title]', 'أتقنت هذا الدرس');
      text('[data-tq-result-text]', 'أجبت ' + score + '. فتح الدرس التالي، وأسئلة هذا الدرس ستعود إليك غدا للتثبيت.');
      acts.innerHTML = r.unlocked_lesson_id
        ? '<a class="tq-btn tq-btn--mastery" href="../' + root.getAttribute('data-tq-course') + '/' + r.unlocked_lesson_id + '">الدرس التالي</a>'
        : '<a class="tq-btn tq-btn--mastery" href="' + baseLessons() + '">عد إلى دروسك</a>';
      show('[data-tq-gate-result]', true);
      load();
      return;
    }

    icon.className = 'tq-icon-box tq-pastel--peach';
    icon.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16v.01"/></svg>';

    if (r.suggest_session) {
      text('[data-tq-result-title]', 'لنسأل معلمك');
      text('[data-tq-result-text]', 'أجبت ' + score + '. المفهوم المتعثر سيصل معلمك ومعه موضعه في الدرس، فيبدأ من حيث تعثرت لا من الصفر.');
      acts.innerHTML = '<a class="tq-btn tq-btn--primary" href="' + baseMessages() + '">اسأل المعلم</a>'
        + '<button class="tq-btn tq-btn--secondary" type="button" data-tq-gate-again>حاول مرة أخرى</button>';
    } else if (r.alternate_explanation_id) {
      text('[data-tq-result-title]', 'نشرحها بطريقة أخرى');
      text('[data-tq-result-text]', 'أجبت ' + score + '. هذا شرح آخر للمفهوم نفسه — ثم أعد المراجعة.');
      acts.innerHTML = '<a class="tq-btn tq-btn--primary" href="../' + root.getAttribute('data-tq-course') + '/' + r.alternate_explanation_id + '">اشرح تاني</a>'
        + '<button class="tq-btn tq-btn--secondary" type="button" data-tq-gate-again>أعد المراجعة</button>';
    } else {
      var at = r.seek_to || 0;
      text('[data-tq-result-title]', 'راجع الدقيقة ' + mmss(at));
      // المحاولة الثانية بلا شرح بديل: لا تعاد كلمات الأولى حرفيا. تكرار
      // النص نفسه يقرأه الطالب على أنه عطب، ويخفي أن الدعم تصاعد فعلا.
      text('[data-tq-result-text]', (r.attempt_no > 1)
        ? 'أجبت ' + score + '. لا يوجد شرح بديل لهذا المفهوم بعد، فارجع إلى اللحظة نفسها بتركيز — وإن تعثرت مرة أخرى نمرر المفهوم إلى معلمك.'
        : 'أجبت ' + score + '. لن نعطيك الإجابة — لكن المفهوم شرح عند هذه اللحظة بالضبط، فارجع إليها ثم أعد المراجعة.');
      acts.innerHTML = '<button class="tq-btn tq-btn--primary" type="button" data-tq-seek="' + at + '">شغل من هناك</button>'
        + '<button class="tq-btn tq-btn--secondary" type="button" data-tq-gate-again>أعد المراجعة</button>';
    }
    /* «راجع إجاباتك» في كل حالة: يلحق بعد بناء أزرار الحالة، فلا
       يكرر في أربعة فروع ولا ينسى في واحد. */
    if (acts && r.attempt_id) {
      acts.insertAdjacentHTML('beforeend',
        ' <button class="tq-btn tq-btn--secondary" type="button" data-tq-open-review="'
        + r.attempt_id + '">راجع إجاباتك</button>');
    }
    show('[data-tq-gate-result]', true);
    $('[data-tq-gate-result]').scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  root.addEventListener('click', function (ev) {
    var seek = ev.target.closest('[data-tq-seek]');
    if (seek) {
      var v = root.querySelector('video'), sec = parseInt(seek.getAttribute('data-tq-seek'), 10) || 0;
      if (v) { v.currentTime = sec; v.play(); v.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
      else alertBox('ارجع إلى الدقيقة ' + mmss(sec) + ' في المشغل.');
      return;
    }
    if (ev.target.closest('[data-tq-gate-again]')) {
      show('[data-tq-gate-result]', false);
      if (startBtn) startBtn.click();
    }
    if (ev.target.closest('[data-tq-retry]')) load();
  });

  function baseLessons() { return location.pathname.replace(/\/lesson\/.*$/, '/lessons'); }
  function baseMessages() { return location.pathname.replace(/\/lesson\/.*$/, '/messages'); }

  function alertBox(msg) {
    var e = $('[data-tq-lesson-error]');
    if (!e) return;
    text('[data-tq-error-msg]', msg);
    e.hidden = false;
    e.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(function () { e.hidden = true; }, 6000);
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  load();
})();

/* ---- مراجعة الإجابات -------------------------------------------------
   طلب ثان بعد التسليم: رد التسليم لا يحمل الإجابات الصحيحة عمدا،
   فالتلميح بالحل أثناء الاختبار يفسد قياسه. وهنا يطلب ما بعده. */
(function () {
  var root = document.querySelector('[data-tq-gate]');
  if (!root) return;
  var GATE = root.getAttribute('data-tq-gate');
  var box  = document.querySelector('[data-tq-gate-review]');
  if (!box) return;

  var list  = box.querySelector('[data-tq-review-list]');
  var score = box.querySelector('[data-tq-review-score]');
  var again = box.querySelector('[data-tq-review-again]');
  var close = box.querySelector('[data-tq-review-close]');

  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  window.tqShowReview = function (attemptId) {
    fetch(GATE + '/review_answers', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ attempt_id: attemptId })
    }).then(function (r) { return r.json(); }).then(function (res) {
      var d = res && (res.data || res);
      if (!d || !d.items) return;

      score.textContent = 'نتيجتك ' + d.score + ' من ' + d.total
        + (d.best > d.score ? ' · أعلى درجة لك ' + d.best : '')
        + (d.tries > 1 ? ' · المحاولة ' + d.tries : '');

      list.innerHTML = d.items.map(function (it) {
        var right = it.is_right;
        var given = (it.given || []).join('، ') || '—';
        var corr  = (it.correct || []).join('، ');
        return '<li class="tq-review__item ' + (right ? 'is-right' : 'is-wrong') + '">'
          + '<p class="tq-review__q">' + esc(it.question) + '</p>'
          + '<p class="tq-review__a"><span>إجابتك:</span> ' + esc(given) + '</p>'
          + (right ? '' : '<p class="tq-review__a tq-review__a--ok"><span>الصواب:</span> ' + esc(corr) + '</p>')
          + '</li>';
      }).join('');

      box.hidden = false;
      box.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }).catch(function () {});
  };

  if (close) close.addEventListener('click', function () { box.hidden = true; });
  /* الإعادة تعيد فتح المراجعة من أولها — والمحرك يزيد رقم المحاولة */
  if (again) again.addEventListener('click', function () {
    box.hidden = true;
    var start = document.querySelector('[data-tq-gate-start]');
    var intro = document.querySelector('[data-tq-gate-intro]');
    var res   = document.querySelector('[data-tq-gate-result]');
    if (res) res.hidden = true;
    if (intro) intro.hidden = false;
    if (start) start.click();
  });
})();

/* فتح المراجعة بالتفويض: الزر يبنى بعد التحميل، فالمستمع على المستند. */
document.addEventListener('click', function (e) {
  var b = e.target.closest && e.target.closest('[data-tq-open-review]');
  if (!b || !window.tqShowReview) return;
  window.tqShowReview(parseInt(b.getAttribute('data-tq-open-review'), 10));
});
