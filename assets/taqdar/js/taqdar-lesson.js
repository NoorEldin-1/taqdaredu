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

  var state = {
    lesson: null, attempt: null, questions: [], watched: 0, position: 0, ticker: null,
    /* أدوات `F2.1`: المشغل نفسه (لضبط السرعة والقفز)، ومقاطع النص،
       وعقد النص المرسومة (لتظليل المقطع الجاري بلا إعادة رسم القائمة). */
    video: null, cues: [], cueNodes: [], activeCue: -1, notes: [],
    /* TQ-COVERAGE — المشغل الموحد وخريطة ما شوهد فعلا.
       `seen` كل الدلاء المعروفة (لا يعاد إرسال دلو أرسل)، و`fresh` ما
       لم يرسل بعد. والفصل بينهما هو ما يجعل النبضة صغيرة ومأمونة
       التكرار: نبضة تفشل تعيد دلاءها إلى `fresh` ولا تضاعف شيئا. */
    player: null, duration: 0, buckets: 0, seen: {}, fresh: [],
    /* TQ-BLIND — ما قاسه المشغل فعلا، وهل وصل منه شيء أصلا.
       `media` طول الوسائط كما أعلنه المشغل (لا كما كتب في القاعدة)،
       و`sawTime` أن نبضة موضع واحدة على الأقل وصلت. وبهما وحدهما يعرف
       أن القياس **تعذر** — فيفتح المخرج المشروط بدل حبس لا نهاية له. */
    media: 0, sawTime: false, blind: false, blindTimer: null,
    completed: false, mastered: false
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
    /* المغلف كله، وصف الدرس فيه تحت `d.lesson`. وكان يقرأ
       `state.lesson.duration_sec` من المغلف فيخرج `undefined` أبدا —
       تحت شرط، فالشرط يصدق دائما ولا أحد يلاحظ. والمدة صارت تأتي من
       المشغل ومن رد الخادم، فلا تقرأ من هنا. */
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
    state.position  = parseInt(p.position_sec, 10) || 0;
    state.completed = !!p.completed_at;
    state.mastered  = !!p.mastered_at;
    state.duration  = parseInt(L.duration_sec, 10) || 0;
    state.buckets   = state.duration > 0 ? Math.ceil(state.duration / BUCKET) : 0;

    /* النسبة من التغطية التي حسبها الخادم، لا من العداد.
       كانت `watch_seconds / duration_sec` — ورقمان يقيسان شيئين
       مختلفين: العداد يجمع الثواني ولو تكررت، والقفل يقرأ التغطية. فمن
       أعاد مشاهدة أول دقيقة عشر مرات كان يقرأ «٪١٠٠» على درس مقفل. */
    var pct = typeof p.percent === 'number' ? p.percent
            : (state.duration ? Math.min(100, Math.round((p.covered_sec || 0) / state.duration * 100)) : 0);
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
    mountTools();

    /* الاختبار يفتح بعد إتمام الدرس لا عند تحميله — انظر `openGate()`. */
    openGate();
  }

  /* ==================================================================
     المشغل — من `TQPlayer`، وواحد لكل مصدر
     ==================================================================
     كان هنا `<iframe>` عار ليوتيوب وفيميو. والإطار العاري لا يعلن شيئا:
     لا حدث تشغيل ولا موضع ولا مدة — فالطالب يشاهد الدرس كاملا ولا تسجل
     له ثانية، ولا يكتب `completed_at`، ولا تفتح البوابة. وكل دروس
     المنصة يوتيوب. وثلاث أدوات موعودة (السرعة، والقفز من النص،
     والملاحظات الموقوتة) كانت تعمل على `<video>` وحده.
  */
  function mountPlayer(pb, L) {
    var frame = $('[data-tq-player-frame]');
    if (!frame) return;
    var url = pb.video_url || pb.audio_url || '';

    if (!url) {
      frame.innerHTML = '<div class="tq-empty"><p class="tq-empty__text">لا يوجد مقطع لهذا الدرس.</p></div>';
      show('[data-tq-declare]', false);
      return;
    }
    if (!window.TQPlayer) {
      frame.innerHTML = '<div class="tq-empty"><p class="tq-empty__text">تعذر تحميل المشغل. حدث الصفحة.</p></div>';
      return;
    }

    /* `?t=<ثانية>` يعلو موضع الاستئناف.
       الروابط التي تأتي من دفتر الأخطاء وخريطة الإتقان تقصد لحظة بعينها
       — لحظة المفهوم الذي أخطأ فيه — فإعادته إلى موضع توقفه تلغي كل
       معنى الرابط وتجعله يبحث عما جيء به إليه. */
    var deep = parseInt((location.search.match(/[?&]t=(\d+)/) || [])[1], 10) || 0;
    var at   = deep > 0 ? deep : (state.position || 0);

    TQPlayer.mount(frame, {
      type: pb.video_type || '', url: url, startAt: at, title: L.title || ''
    }).then(function (pl) {
      state.player = pl;
      /* `state.video` يبقى للتوافق: النص والملاحظات كانا يمسانه مباشرة.
         والعنصر الأصلي وحده يملكه، وما سواه يمر بالواجهة. */
      state.video  = pl.el || null;

      pl.on('time', onTime);
      pl.on('play',  startTicker);
      pl.on('pause', stopTicker);
      pl.on('ended', function () { stopTicker(); flush(true); });

      /* المدة حدث قائم بنفسه لا سطر داخل `ready`.
         يوتيوب لا يعرف مدة فيديوه عند الجهوزية — يعلنها بعد أن تحمل
         بيانات وسائطه، أي بعد بدء التشغيل عادة. وكان يسأل مرة واحدة
         عند `ready` فيقرأ صفرا، وصفر المدة يعطل السلسلة كلها: لا دلاء
         تعد، فلا تغطية تسجل، فلا إتمام يكتب، فلا اختبار يفتح. */
      pl.on('duration', onDuration);

      /* ضبط السرعة يعرض حيث يعمل فعلا — والآن يعمل على يوتيوب وفيميو
         أيضا، لا على مشغل المنصة وحده. وزر يضغط ولا يفعل «واجهة قاضية»
         تنهى عنها الوثيقة نصا، فيخفى حيث لا يعمل. */
      show('[data-tq-speed-grp]', pl.canRate());

      /* ما لا يعلن موضعه يقال صراحة: زر إقرار بدل شريط تقدم يكذب.

         و«يقاس» صفتان لا واحدة، وقد تختلفان:

           `pl.kind`     ما استطاعه المشغل في هذا المتصفح الآن
           `L.trackable` ما يعده الخادم مقيسا، من `video_type` المخزن

         و`confirm_complete` تحكم بالثانية: ترفض الإقرار على مصدر تعده
         مقيسا حتى تمضي مهلة العجز. فلو عرض الزر بناء على الأولى وحدها
         لضغطه من ركب درسا نوعه `youtube` ورابطه درايف — فيرى رفضا على
         زر عرض عليه للتو. فالمهلة تنتظر متى اختلفتا. */
      var blind = (pl.kind === 'none');
      var trackable = !!(L && L.trackable);

      /* نبضة الوصول — وهي شهادة العجز لا إعلان الحضور.
         الخادم يختم `blind_at` عند أول نبضة لا يصحبها قياس، ويمحوه عند
         أول قياس يصل. فهذه النبضة تبدأ عد المهلة **من الخادم لا من
         المتصفح**: لو بدأ العد هنا لكان تعديل رقم في جافاسكربت كافيا
         لأخذ المخرج في الحال. وأول مدة يعلنها المشغل تمحو الختم، فلا
         أثر لها على من يعمل مشغله. */
      flush(false, true);

      if (blind && !trackable) {
        /* درايف وإطار خارجي: معروف من أول لحظة أنه لا يقاس، والخادم
           يوافق — فلا انتظار في انتظاره فائدة. */
        goBlind('unmeasurable');
      } else {
        /* ومصدر **يفترض** أنه يقاس قد لا يقيس: سكربت يوتيوب يحجب في
           شبكة مدرسة، والفيديو يحذف من مصدره، والإطار يرفض التضمين.
           وحينها لا موضع ولا مدة ولا خطأ — شاشة صامتة وشريط لا يتحرك
           ودرس تال مقفل إلى الأبد. فينتظر دقيقتين، فإن لم تصل نبضة
           واحدة ولا مدة قيل ذلك صراحة وفتح المخرج. */
        state.blindTimer = setTimeout(function () {
          if (!state.sawTime && !state.media) goBlind('nosignal');
        }, BLIND_AFTER);
      }
    });
  }

  /* ---- التقدم -------------------------------------------------------
     TQ-COVERAGE — العداد وحده يكذب.
     كان يجمع خمس ثوان لكل نبضة مؤقت ما دام المشغل يعمل، فالسحب إلى آخر
     الفيديو ثم تركه دقيقة يكمل درسا لم يشاهد. فالمقياس الآن **تغطية**:
     أي أجزاء الدرس مر عليها التشغيل فعلا — دلو لكل عشر ثوان، يعلم حين
     يمر عليه الموضع. والإتمام أن تعلم منها نسبة `lesson_complete_ratio`،
     والقرار في الخادم لا هنا.
  */
  var BUCKET = 10;

  /**
   * بعدها يقال «تعذر القياس» إن لم تصل نبضة ولا مدة.
   *
   * وهي أطول من مهلة الخادم (`BLIND_GRACE`، دقيقتان) بهامش: الخادم يبدأ
   * عده من نبضة الوصول، فلو تساوى الرقمان لرد الطلب الأول بالرفض لفارق
   * أجزاء من الثانية — ويقرأ الطالب «لا يقبل» على زر عرض عليه للتو.
   */
  var BLIND_AFTER = 135000;

  function onTime(t) {
    var sec = Math.floor(t || 0);
    if (sec < 0) return;
    state.position = sec;
    state.sawTime = true;
    markCue(sec);

    /* رقم الدلو لا يحتاج المدة — `floor(sec/10)` وحده. وكان الشرط
       `state.buckets > 0` يسبقه، فما شوهد قبل أن تعرف المدة يضيع كله:
       يوتيوب يعلن مدته بعد بدء التشغيل، فأول ثوان الدرس — وهي التي
       يشاهدها كل طالب — كانت تسقط بلا أثر. والحد الأعلى يفرضه الخادم
       على خريطته، فدلو خارج المدى يهمل هناك ولا يفسد شيئا. */
    var b = Math.floor(sec / BUCKET);
    if (b < 0 || state.seen[b]) return;
    if (state.buckets > 0 && b >= state.buckets) return;
    state.seen[b] = 1;
    state.fresh.push(b);
  }

  /**
   * المدة كما قاسها المشغل — لا كما كتبت في القاعدة.
   *
   * وهما ليسا واحدا: معلم يكتب `00:12:00` على فيديو طوله دقيقتان
   * وثمان وأربعون ثانية يحبس طلابه إلى الأبد — لا يبلغون النسبة أبدا
   * ولا شيء يقول لماذا. فالقياس يرسل إلى الخادم، وهو من يقرر أيهما
   * يعتمد (يحتاج اتفاق طالبين مستقلين قبل أن يصحح صفا)، ونحن نأخذ
   * جوابه. والرقم الذي يقرؤه الطالب يجب أن يكون الرقم الذي يقرؤه
   * القفل — فلا يحسب هنا رقم ثان.
   */
  function onDuration(d) {
    d = Math.round(d || 0);
    if (d <= 0 || state.media === d) return;
    state.media = d;

    /* والمدة تظهر في الشاشة فورا: انتظار الخادم يترك «00:00:00» أمام
       من يشاهد فيديو يرى مدته في مشغله. */
    if (!state.duration) text('[data-tq-lesson-duration]', iso(hms(d)));

    if (!state.duration) {
      state.duration = d;
      state.buckets  = Math.ceil(d / BUCKET);
    }
    flush(false, true);
  }

  /** ثوان إلى `HH:MM:SS` — للعرض وحده. */
  function hms(sec) {
    sec = Math.max(0, parseInt(sec, 10) || 0);
    var h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60;
    return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
  }

  /**
   * يعلن أن القياس متعذر، ويفتح المخرج المشروط.
   *
   * `unmeasurable` — درايف وإطار خارجي: معروف من أول لحظة أنه لا يقاس.
   * `nosignal`     — مصدر يفترض أنه يقاس ولم يعلن شيئا في دقيقتين:
   *                  سكربت محجوب، أو فيديو حذف، أو تضمين مرفوض.
   *
   * والفرق يقال للطالب بنصه: «هذا النوع لا يقاس» غير «تعذر قياس
   * مشاهدتك» — والثانية تدعوه إلى تحديث الصفحة قبل أن يقر.
   *
   * والإقرار **يسجل إقرارا لا قياسا**: `declared_at` في الصف، فيعرف
   * المعلم أي إتمام قيس وأي إتمام أقر — ولا يظن أن كل ٪١٠٠ سواء.
   */
  function goBlind(why) {
    if (state.blind) return;
    state.blind = true;
    if (state.blindTimer) { clearTimeout(state.blindTimer); state.blindTimer = null; }

    if (why === 'nosignal') {
      text('[data-tq-blind-title]', 'تعذر قياس مشاهدتك لهذا الدرس');
      text('[data-tq-blind-body]',
        'المشغل لم يعلن موضع التشغيل ولا مدة المقطع — قد يكون الفيديو محجوبا على شبكتك '
        + 'أو حذف من مصدره. جرب تحديث الصفحة أولا؛ فإن بقي الحال، أعلن إتمامك ليفتح لك '
        + 'الاختبار. وسيصل معلمك أن هذا الإتمام أقر ولم يقس.');
    }
    show('[data-tq-blind-note]', true);
    show('[data-tq-declare]', !state.completed);
  }

  function startTicker() {
    if (state.ticker) return;
    state.ticker = setInterval(function () {
      state.watched += 5;
      if (state.watched >= 15) flush(false);
    }, 5000);
  }
  function stopTicker() {
    if (state.ticker) { clearInterval(state.ticker); state.ticker = null; }
    flush(false);
  }

  function flush(ended, force) {
    if (!state.watched && !state.fresh.length && !ended && !force) return;
    var delta = state.watched; state.watched = 0;
    var cov = state.fresh;     state.fresh = [];

    call('progress', {
      lesson_id: LESSON,
      position_sec: state.position,
      watched_delta: delta,
      covered: cov,
      /* المقاس يرسل باسمه: `media_sec` ما أعلنه المشغل، و`duration_sec`
         ما تعمل عليه هذه الشاشة الآن. والخادم يقابل بينهما ويرد الرقم
         المعتمد، فلا يبقى في المتصفح أساس ثان للنسبة. */
      media_sec: state.media || 0,
      duration_sec: state.duration || 0
    }).then(function (r) {
      paintProgress(r);
      if (r && r.completed_at && !state.completed) {
        state.completed = true;
        show('[data-tq-declare]', false);
        openGate();
      } else if (ended) {
        openGate();
      }
    }).catch(function () {
      /* التقدم لا يوقف المشاهدة. والدلاء تعاد إلى الطابور: إسقاطها يعني
         أن انقطاعا لحظة يمحو دقيقة شاهدها الطالب فعلا. */
      state.fresh = cov.concat(state.fresh);
      state.watched += delta;
    });
  }

  /** شريط التقدم — من رد الخادم لا من حساب في المتصفح. */
  function paintProgress(r) {
    if (!r) return;

    /* الأساس من الخادم لا من هنا.
       هو من يقرر أي مدة تعتمد (المكتوبة أم المقيسة)، ونحن نعيد ضبط
       عدد الدلاء عليها — وإلا حسب المتصفح نسبته على أساس والقفل على
       آخر، فيقف «٪١٠٠» أمام درس مقفل. */
    if (typeof r.duration_sec === 'number' && r.duration_sec > 0
        && r.duration_sec !== state.duration) {
      state.duration = r.duration_sec;
      state.buckets  = Math.ceil(state.duration / BUCKET);
      text('[data-tq-lesson-duration]', iso(hms(state.duration)));
    }

    var pw = $('[data-tq-lesson-progress]');
    if (pw && typeof r.percent === 'number') {
      pw.innerHTML = '<div class="tq-progress"><div class="tq-progress__track">'
        + '<div class="tq-progress__fill" style="inline-size:' + r.percent + '%"></div></div>'
        + '<span class="tq-progress__value">' + iso(r.percent + '%') + '</span></div>';
    }
    var badge = $('[data-tq-lesson-badge]');
    if (badge && r.completed_at) {
      badge.innerHTML = '<span class="tq-badge tq-badge--progress">شوهد</span>';
    }
  }

  /**
   * يفتح بطاقة الاختبار — بعد إتمام الدرس لا عند تحميله.
   *
   * كانت تعرض فور فتح الصفحة، فيقرأ الطالب «هل فهمت؟» قبل أن يشاهد
   * دقيقة. والاختبار بوابة الدرس التالي، فموضعه بعد الدرس.
   */
  function openGate() {
    var has = !!(state.lesson && state.lesson.review);
    var done = state.completed || (state.lesson && state.lesson.mastered);
    show('[data-tq-gate-intro]', has && done && !state.mastered);
    show('[data-tq-gate-wait]',  has && !done);
  }

  addEventListener('beforeunload', function () {
    if (state.watched || state.fresh.length) flush(false);
  });

  /* ---- إقرار الإتمام: للمصادر التي لا تعلن موضعها ---- */
  var declareBtn = $('[data-tq-declare]');
  if (declareBtn) declareBtn.addEventListener('click', function () {
    declareBtn.setAttribute('data-loading', 'true');
    call('complete', { lesson_id: LESSON }).then(function () {
      declareBtn.removeAttribute('data-loading');
      state.completed = true;
      show('[data-tq-declare]', false);
      paintProgress({ percent: 100, completed_at: 1 });
      openGate();
    }).catch(function (e) {
      declareBtn.removeAttribute('data-loading');
      alert(e && e.message ? e.message : 'تعذر تسجيل الإتمام.');
    });
  });

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
      /* TQ-QIMG · صورة السؤال — للمعادلة والرسم البياني ولقطة الشاشة.
         و`alt` فارغة عمدا: الصورة **هي** السؤال لا زينة له، ووصفها
         بنص بديل يكتب السؤال مرتين أو يفشي جوابه. */
      var img = q.image
        ? '<img class="tq-qimg" src="' + escapeHtml(q.image) + '" alt="" loading="lazy" decoding="async">'
        : '';
      return '<fieldset style="border:0;padding:0;margin:0 0 var(--tq-space-xl)">'
        + '<legend class="tq-strong" style="margin-block-end:var(--tq-space-m)">'
        + iso(i + 1) + '. ' + escapeHtml(q.title) + '</legend>' + img + opts + '</fieldset>';
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

  /* ==================================================================
     أدوات المشغل — `F2.1`
     ==================================================================
     ثلاث أدوات، وشرط الوثيقة عليها واحد: «كل أداة تشتغل قطعيا لا واجهة
     قاضية». ولذلك ما لا مصدر له **يخفى** ولا يعرض معطلا: شريط النص لا
     يظهر لدرس بلا نص، وضابط السرعة لا يظهر على إطار يوتيوب.
     ================================================================== */

  function mountTools() {
    var bar = $('[data-tq-ptools]');
    if (bar) bar.hidden = false;

    loadTranscript();
    loadNotes();
  }

  /* ---- 1 · السرعة ------------------------------------------------- */
  function setRate(rate) {
    /* عبر الواجهة لا على العنصر: يوتيوب وفيميو يقبلان تغيير السرعة عبر
       واجهتيهما، وكان الزر يخفى عليهما لأن `state.video` فارغ. */
    if (!state.player || !state.player.setRate(rate)) return;
    var btns = root.querySelectorAll('[data-tq-rate]');
    for (var i = 0; i < btns.length; i++) {
      var on = parseFloat(btns[i].getAttribute('data-tq-rate')) === rate;
      btns[i].classList.toggle('is-on', on);
      btns[i].setAttribute('aria-pressed', on ? 'true' : 'false');
    }
  }

  /* ---- 2 · النص القابل للبحث -------------------------------------- */
  function loadTranscript() {
    call('transcript/' + LESSON).then(function (d) {
      state.cues = (d && d.cues) || [];
      if (!state.cues.length) return;           // بلا نص: لا شريط ولا زر

      show('[data-tq-tr-grp]', true);
      renderCues('');
      var c = $('[data-tq-tr-count]');
      if (c) c.textContent = 'نص الدرس في ' + state.cues.length + ' مقطعا. اضغط أي مقطع لتشغيله من موضعه.';
    }).catch(function () { /* النص إضافة؛ غيابه لا يمس الدرس */ });
  }

  function renderCues(q) {
    var list = $('[data-tq-tr-list]');
    if (!list) return;

    var needle = (q || '').trim();
    var rows = needle
      ? state.cues.filter(function (c) { return c.text.indexOf(needle) !== -1; })
      : state.cues;

    show('[data-tq-tr-none]', needle !== '' && rows.length === 0);

    list.innerHTML = rows.map(function (c) {
      var body = escapeHtml(c.text);
      if (needle) {
        /* التظليل على النص **بعد** الهروب: التظليل قبله يحقن وسما من
           صندوق بحث. والبحث حرفي لا نمطي، فلا يهرب المستخدم من تعبير. */
        body = body.split(escapeHtml(needle)).join('<mark>' + escapeHtml(needle) + '</mark>');
      }
      return '<li><button class="tq-transcript__cue" type="button" data-tq-cue="' + c.at_second + '">'
           + '<span class="tq-transcript__t">' + c.at_label + '</span>'
           + '<span class="tq-transcript__x">' + body + '</span></button></li>';
    }).join('');

    state.cueNodes = list.querySelectorAll('[data-tq-cue]');
    state.activeCue = -1;
  }

  /* المقطع الجاري: بحث خطي على قائمة مرتبة يكفي — مئتا مقطع في أطول
     درس، والنداء أربع مرات في الثانية. والتظليل لا يعاد رسمه إلا حين
     يتغير المقطع فعلا، وإلا ومض النص مع كل نبضة. */
  function markCue(sec) {
    if (!state.cueNodes.length) return;

    var idx = -1;
    for (var i = 0; i < state.cueNodes.length; i++) {
      if (parseInt(state.cueNodes[i].getAttribute('data-tq-cue'), 10) <= sec) idx = i;
      else break;
    }
    if (idx === state.activeCue) return;

    if (state.activeCue >= 0 && state.cueNodes[state.activeCue]) {
      state.cueNodes[state.activeCue].classList.remove('is-now');
    }
    state.activeCue = idx;
    if (idx >= 0) state.cueNodes[idx].classList.add('is-now');
  }

  /* ---- 3 · الملاحظات الموقوتة ------------------------------------- */
  function loadNotes() {
    call('notes/' + LESSON).then(function (d) {
      state.notes = (d && d.notes) || [];
      renderNotes();
    }).catch(function () {});
  }

  function renderNotes() {
    var card = $('[data-tq-notes-card]'), box = $('[data-tq-notes]');
    if (!box) return;

    if (card) card.hidden = state.notes.length === 0;
    var n = $('[data-tq-notes-count]');
    if (n) n.textContent = state.notes.length ? iso(state.notes.length) : '';

    box.innerHTML = state.notes.map(function (o) {
      return '<li>'
        + '<button class="tq-notes__jump" type="button" data-tq-cue="' + o.at_second + '">'
        + o.at_label + '</button>'
        + '<span class="tq-notes__b">' + escapeHtml(o.body) + '</span>'
        + '<button class="tq-notes__del" type="button" data-tq-note-del="' + o.id
        + '" aria-label="احذف الملاحظة">&times;</button></li>';
    }).join('');
  }

  function openNoteForm() {
    var form = $('[data-tq-noteform]');
    if (!form) return;
    /* اللحظة تلتقط عند فتح النموذج لا عند الحفظ: بين الاثنين يكتب
       الطالب سطرين والفيديو يمضي، فتحفظ الملاحظة عند لحظة لا تخصها.
       والفيديو يوقف لأن من يكتب لا يسمع. */
    var at = state.position || 0;
    form.setAttribute('data-tq-at', at);
    if (state.player) state.player.pause();

    var lbl = $('[data-tq-note-at]');
    if (lbl) lbl.textContent = 'ملاحظة عند ' + mmss(at);
    form.hidden = false;
    var ta = $('[data-tq-note-body]');
    if (ta) { ta.value = ''; ta.focus(); }
  }

  function saveNote() {
    var form = $('[data-tq-noteform]');
    var ta = $('[data-tq-note-body]');
    if (!form || !ta || !ta.value.trim()) return;

    call('note_add', {
      lesson_id: LESSON,
      at_second: parseInt(form.getAttribute('data-tq-at'), 10) || 0,
      body: ta.value
    }).then(function (d) {
      state.notes = (d && d.notes) || state.notes;
      renderNotes();
      form.hidden = true;
      ta.value = '';
    }).catch(function (e) { alertBox(e.message || 'تعذر حفظ الملاحظة.'); });
  }

  function deleteNote(id) {
    call('note_delete', { note_id: id, lesson_id: LESSON }).then(function (d) {
      state.notes = (d && d.notes) || [];
      renderNotes();
    }).catch(function () {});
  }

  root.addEventListener('submit', function (ev) {
    if (ev.target.matches('[data-tq-noteform]')) { ev.preventDefault(); saveNote(); }
  });

  var trSearch = $('[data-tq-tr-search]');
  if (trSearch) {
    /* تأخير قصير: الرسم عند كل ضغطة على قائمة من مئتي مقطع يقطع الكتابة
       في الجوال. */
    var timer = null;
    trSearch.addEventListener('input', function () {
      clearTimeout(timer);
      var q = trSearch.value;
      timer = setTimeout(function () {
        renderCues(q);
        if (q.trim()) show('[data-tq-transcript]', true);
      }, 180);
    });
  }

  root.addEventListener('click', function (ev) {
    /* القفز إلى ثانية: مصدره ثلاثة — قرار البوابة، ومقطع نص، وملاحظة.
       وثلاثتها فعل واحد، فيتشاركان `data-tq-seek`/`data-tq-cue` نفسه. */
    var cue = ev.target.closest('[data-tq-cue]');
    if (cue) {
      var at = parseInt(cue.getAttribute('data-tq-cue'), 10) || 0;
      /* القفز صار يعمل على كل مصدر يعلن موضعه — لا على `<video>` وحده.
         وما لا يعلنه (درايف والإطار الخارجي) يقال لصاحبه أين يذهب بدل
         زر يضغط ولا يتحرك شيء. */
      if (state.player && state.player.kind !== 'none') {
        state.player.seek(at);
        var frame = $('[data-tq-player-frame]');
        if (frame) frame.scrollIntoView({ behavior: 'smooth', block: 'center' });
      } else {
        alertBox('ارجع إلى الدقيقة ' + mmss(at) + ' في المشغل.');
      }
      return;
    }

    var rate = ev.target.closest('[data-tq-rate]');
    if (rate) { setRate(parseFloat(rate.getAttribute('data-tq-rate')) || 1); return; }

    var trToggle = ev.target.closest('[data-tq-tr-toggle]');
    if (trToggle) {
      var panel = $('[data-tq-transcript]');
      var open = panel && panel.hidden;
      show('[data-tq-transcript]', open);
      trToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      return;
    }

    if (ev.target.closest('[data-tq-note-add]')) { openNoteForm(); return; }
    if (ev.target.closest('[data-tq-note-cancel]')) { show('[data-tq-noteform]', false); return; }

    var del = ev.target.closest('[data-tq-note-del]');
    if (del) { deleteNote(parseInt(del.getAttribute('data-tq-note-del'), 10)); return; }
  });

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
