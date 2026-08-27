/**
 * مشغل واحد لكل مصدر.
 *
 * ----------------------------------------------------------------------
 * لماذا
 *
 * كان `mountPlayer()` يطبع `<iframe>` عاريا ليوتيوب وفيميو. والإطار
 * العاري لا يعلن شيئا: لا حدث تشغيل، ولا موضع، ولا مدة. فالطالب يشاهد
 * الدرس كاملا ولا تسجل له ثانية واحدة، ولا يكتب `completed_at` أبدا،
 * ولا تفتح بوابة الإتقان — لأنها تنتظر إتماما لا يقع. وكل الدروس في
 * القاعدة يوتيوب.
 *
 * والمشغل الوحيد الذي كان يعلن موضعه هو `<video>` الأصلي، وأدوات
 * الشاشة كلها مبنية عليه: ضبط السرعة، والقفز من نص الدرس، والملاحظات
 * الموقوتة، والعودة إلى ثانية المفهوم الذي أخطأ فيه. فثلاث أدوات
 * موعودة لا تعمل على المصدر الذي عليه كل المحتوى.
 *
 * ----------------------------------------------------------------------
 * الواجهة
 *
 *   TQPlayer.mount(el, {type, url, startAt, title}) -> Promise<player>
 *
 *   player.kind          'api' | 'native' | 'none'
 *   player.on(ev, fn)    ready · duration · play · pause · ended · time
 *   player.duration()    ثوان، أو صفر إن لم تعرف بعد
 *   player.currentTime()
 *   player.seek(sec)
 *   player.setRate(r)    ترد false إن لم يدعمها المصدر
 *   player.canRate()
 *   player.destroy()
 *
 * وثلاثة أنواع من التتبع لا نوعان:
 *
 *   api     يوتيوب وفيميو — مشغل من طرف آخر له واجهة برمجة تعلن موضعه
 *   native  عنصر `<video>`/`<audio>` في صفحتنا — الأدق
 *   none    درايف وإطار خارجي — لا موضع يقرأ، والطالب يعلن إتمامه
 *
 * والثالث يعلن عن نفسه صراحة (`kind === 'none'`) فتعرض الشاشة زر «أنهيت
 * الدرس» بدل أن تعد بقياس لا يقع. وإخفاء العجز أسوأ من إعلانه: طالب
 * ينتظر شريط تقدم لا يتحرك يظن العطب في حسابه.
 *
 * ----------------------------------------------------------------------
 * `ready` و`duration` لاصقان
 *
 * وهما وحدهما كذلك، لأنهما **حالة لا لحظة**: من سأل «أجاهز؟» بعد أن صار
 * جاهزا يجب أن يقال له نعم، لا أن يسكت عنه لأنه تأخر. وبغير ذلك يضيع
 * الحدثان على يوتيوب وفيميو كليهما — انظر TQ-READY-LOST أدناه.
 *
 * و`duration` يعلن مرة واحدة، وقد يتأخر عن `ready` بثوان: يوتيوب لا
 * يعرف مدة فيديوه إلا بعد أن تحمل بيانات وسائطه.
 */
(function (global) {
  'use strict';

  /* ---- تحميل سكربت خارجي مرة واحدة ---------------------------------
     يوتيوب وفيميو يوزعان مشغليهما سكربتا من نطاقهما، ولا سبيل إلى قراءة
     موضع التشغيل بغيره. والصفحة تضمن إطاريهما أصلا، فلا جديد. */
  var loaded = {};
  function loadScript(src) {
    if (loaded[src]) return loaded[src];
    loaded[src] = new Promise(function (ok, no) {
      var s = document.createElement('script');
      s.src = src;
      s.async = true;
      s.onload = function () { ok(); };
      s.onerror = function () { no(new Error('script: ' + src)); };
      document.head.appendChild(s);
    });
    return loaded[src];
  }

  /* ---- ناقل أحداث صغير، وحدثان منه لاصقان -------------------------
     TQ-READY-LOST — `ready` و`duration` **حالة لا لحظة**.

     كان `mountYouTube` ينادي `ok(wrap())` ثم `bus.fire('ready')` في
     السطر التالي — والأول يحل وعدا تنفذ توابعه في دورة صغرى تالية،
     والثاني يقع **الآن**. فالصفحة تسجل مستمعها بعد أن يكون الحدث قد
     وقع ومضى، ولا يصلها شيء أبدا. وفيميو مثله: `bus.fire('ready')`
     داخل `then` الذي يرد المشغل.

     ونتيجته أن الكتلة التي تعلن المدة **لم تنفذ مرة واحدة** على يوتيوب
     ولا فيميو — وهما كل محتوى المنصة. فيبقى `lesson.duration_sec`
     صفرا، فلا دلاء تحسب، فلا تغطية تسجل، فلا `completed_at` يكتب،
     فلا اختبار يفتح ولا درس تال. عطل واحد في ترتيب سطرين يوقف السلسلة
     كلها.

     والعلاج ليس تأخير الإطلاق — من ينتظر دورة يخسرها من اشترك مبكرا —
     بل أن يحفظ الحدثان آخر قيمة لهما، فمن اشترك بعد وقوعهما علمهما في
     الحال. */
  var STICKY = { ready: 1, duration: 1 };

  function emitter() {
    var map = {}, latched = {};
    return {
      on: function (ev, fn) {
        (map[ev] = map[ev] || []).push(fn);
        if (STICKY[ev] && Object.prototype.hasOwnProperty.call(latched, ev)) {
          try { fn(latched[ev]); } catch (e) { /* مستمع متأخر يرمي لا يهم غيره */ }
        }
      },
      fire: function (ev, a) {
        if (STICKY[ev]) latched[ev] = a;
        (map[ev] || []).forEach(function (fn) {
          try { fn(a); } catch (e) { /* مستمع يرمي لا يوقف الباقين */ }
        });
      }
    };
  }

  /* ---- إعلان المدة: يسأل حتى يعرف، لا مرة واحدة -------------------
     يوتيوب يرد `getDuration()` صفرا حتى تحمل بيانات الوسائط، وهي تحمل
     بعد بدء التشغيل عادة لا عند الجهوزية. فسؤال واحد عند `ready` يعطي
     صفرا في أكثر الأحوال — والصفر يعطل قياس التقدم كله، لأن خريطة
     الدلاء تحتاج عددها وعددها من المدة.

     فالسؤال يعاد: كل نصف ثانية إلى نصف دقيقة، ومع كل نبضة موضع بعدها.
     ويعلن مرة واحدة وحدها (`duration` لاصق)، ثم يكف. */
  function announceDuration(bus, get) {
    var done = false, tries = 0, timer = null;

    function look() {
      if (done) return true;
      var d = Math.round(get() || 0);
      if (!isFinite(d) || d <= 0) return false;
      done = true;
      if (timer) { clearInterval(timer); timer = null; }
      bus.fire('duration', d);
      return true;
    }

    if (!look()) {
      timer = setInterval(function () {
        if (look() || ++tries > 60) { clearInterval(timer); timer = null; }
      }, 500);
      /* والموضع يعلن قبل المدة أحيانا: أول نبضة تشغيل مناسبة للسؤال. */
      bus.on('time', function () { look(); });
    }
    return function () { if (timer) clearInterval(timer); };
  }

  /* ---- تعرف المصدر -------------------------------------------------
     النوع المعلن يعلو، والرابط يفصل حين لا يعلن: درس قديم قد يحمل
     `video_type` فارغا، ورابط يوتيوب يبقى رابط يوتيوب. */
  function detect(type, url) {
    var t = String(type || '').toLowerCase();
    var u = String(url || '');

    if (t === 'youtube' || /(?:youtube\.com|youtu\.be)/i.test(u)) return 'youtube';
    if (t === 'vimeo'   || /vimeo\.com/i.test(u))                 return 'vimeo';
    if (t === 'google_drive' || /drive\.google\.com/i.test(u))    return 'drive';
    if (t === 'audio')                                            return 'audio';
    if (t === 'iframe')                                           return 'embed';
    if (/\.(mp4|webm|ogv|ogg|m4v|mov)(\?|#|$)/i.test(u))          return 'html5';
    if (/\.(mp3|m4a|wav|oga|aac)(\?|#|$)/i.test(u))               return 'audio';
    /* الملف الموقع يخرج من `taqdar_gate/media/<رمز>` بلا امتداد، وهو
       دائما وسائط تبثها المنصة نفسها. */
    if (/taqdar_gate\/media\//.test(u))                           return 'html5';
    return t === 'html5' || t === 'system' ? 'html5' : 'embed';
  }

  function youtubeId(u) {
    var m = String(u).match(/(?:v=|youtu\.be\/|embed\/|shorts\/)([\w-]{6,})/);
    return m ? m[1] : '';
  }
  function vimeoId(u) {
    var m = String(u).match(/vimeo\.com\/(?:video\/)?(\d+)/);
    return m ? m[1] : '';
  }
  function driveId(u) {
    var m = String(u).match(/\/d\/([\w-]+)/) || String(u).match(/[?&]id=([\w-]+)/);
    return m ? m[1] : '';
  }

  /* ==================================================================
     يوتيوب — عبر IFrame Player API
     ================================================================== */
  function mountYouTube(el, opt, bus) {
    var id = youtubeId(opt.url);
    if (!id) return Promise.reject(new Error('youtube: لا معرف في الرابط'));

    return loadScript('https://www.youtube.com/iframe_api').then(function () {
      return new Promise(function (ok, no) {
        /* الواجهة تعلن جهوزيتها بدالة عامة واحدة، وقد تكون نودي عليها
           قبل أن نصل. فالشرطان معا: إما جاهزة الآن، وإما ننتظر النداء
           ونحفظ ما كان قبلنا. */
        var start = function () {
          var host = document.createElement('div');
          el.innerHTML = '';
          el.appendChild(host);

          var yt = new global.YT.Player(host, {
            videoId: id,
            playerVars: {
              rel: 0, modestbranding: 1, playsinline: 1,
              start: Math.max(0, Math.floor(opt.startAt || 0))
            },
            events: {
              onReady: function () {
                ok(wrap());
                bus.fire('ready');
                /* والمدة تسأل حتى تعرف: يوتيوب يردها صفرا هنا. */
                announceDuration(bus, function () {
                  return yt.getDuration ? yt.getDuration() : 0;
                });
              },
              onStateChange: function (e) {
                var S = global.YT.PlayerState;
                if (e.data === S.PLAYING) bus.fire('play');
                else if (e.data === S.PAUSED)  bus.fire('pause');
                else if (e.data === S.ENDED)   { bus.fire('pause'); bus.fire('ended'); }
              },
              onError: function () { no(new Error('youtube: تعذر تشغيل الفيديو')); }
            }
          });

          /* يوتيوب لا يرسل حدث موضع: يعلن التغير في الحالة وحدها. فنسأله
             كل ثانية ما دام يعمل — والسؤال رخيص (قراءة من كائن محلي، لا
             نداء شبكة). */
          var timer = null;
          bus.on('play', function () {
            if (timer) return;
            timer = setInterval(function () {
              bus.fire('time', yt.getCurrentTime ? yt.getCurrentTime() : 0);
            }, 1000);
          });
          var stop = function () { if (timer) { clearInterval(timer); timer = null; } };
          bus.on('pause', stop);
          bus.on('ended', stop);

          function wrap() {
            return {
              kind: 'api',
              provider: 'youtube',
              duration: function () { return yt.getDuration ? (yt.getDuration() || 0) : 0; },
              currentTime: function () { return yt.getCurrentTime ? (yt.getCurrentTime() || 0) : 0; },
              seek: function (s) { if (yt.seekTo) { yt.seekTo(s, true); } },
              canRate: function () { return true; },
              setRate: function (r) {
                if (!yt.setPlaybackRate) return false;
                yt.setPlaybackRate(parseFloat(r));
                return true;
              },
              pause: function () { if (yt.pauseVideo) yt.pauseVideo(); },
              destroy: function () { stop(); try { yt.destroy(); } catch (e) {} }
            };
          }
        };

        if (global.YT && global.YT.Player) start();
        else {
          var prev = global.onYouTubeIframeAPIReady;
          global.onYouTubeIframeAPIReady = function () {
            if (typeof prev === 'function') prev();
            start();
          };
        }
      });
    });
  }

  /* ==================================================================
     فيميو — عبر Player SDK
     ================================================================== */
  function mountVimeo(el, opt, bus) {
    var id = vimeoId(opt.url);
    if (!id) return Promise.reject(new Error('vimeo: لا معرف في الرابط'));

    return loadScript('https://player.vimeo.com/api/player.js').then(function () {
      el.innerHTML = '';
      /* TQ-VIMEO-BOX — `responsive: true` **يبني صندوق نسبة ثانيا**.
         الخيار يطلب من oEmbed وسما ملفوفا: `<div style="padding:56.25% 0 0 0
         ;position:relative"><iframe …></div>`. وحاويتنا `.tq-player__frame`
         صندوق نسبة أصلا (`padding-block-end:56.25%`)، فيجتمع الصندوقان:
         الإطار يرسم بطوله الطبيعي في أعلى الحاوية، ثم تليه حشوة الحاوية
         كلها فراغا داكنا بطول شاشة — وهو ما ظهر في الإنتاج.
         وأسوأ منه أن `.tq-player__frame > iframe` لا يطابق شيئا حينها:
         الإطار صار حفيدا لا ابنا، فلا يمتد ولا يتموضع.
         فالنسبة تترك لحاويتنا وحدها، والسمة تخرج إطارا مباشرا. */
      var vp = new global.Vimeo.Player(el, { id: id, playsinline: true });

      vp.on('play',        function () { bus.fire('play'); });
      vp.on('pause',       function () { bus.fire('pause'); });
      vp.on('ended',       function () { bus.fire('pause'); bus.fire('ended'); });
      vp.on('timeupdate',  function (d) { bus.fire('time', d && d.seconds); });

      var dur = 0, now = 0;
      vp.getDuration().then(function (d) { dur = d || 0; }).catch(function () {});
      vp.on('timeupdate', function (d) { now = (d && d.seconds) || 0; });
      /* `getDuration()` وعد قد يحل بعد أن يرد المشغل، فلا يسأل مرة. */
      announceDuration(bus, function () { return dur; });

      return vp.ready().then(function () {
        if (opt.startAt > 0) { try { vp.setCurrentTime(opt.startAt); } catch (e) {} }
        bus.fire('ready');
        return {
          kind: 'api',
          provider: 'vimeo',
          duration: function () { return dur; },
          currentTime: function () { return now; },
          seek: function (s) { try { vp.setCurrentTime(s); } catch (e) {} },
          canRate: function () { return true; },
          /* فيميو يرفض تغيير السرعة على فيديو صاحبه منعها، فيرمي —
             والوعد المرفوض بلا معالج ضجيج في الكونسول لا أكثر. */
          setRate: function (r) { try { vp.setPlaybackRate(parseFloat(r)); } catch (e) { return false; } return true; },
          pause: function () { try { vp.pause(); } catch (e) {} },
          destroy: function () { try { vp.destroy(); } catch (e) {} }
        };
      });
    });
  }

  /* ==================================================================
     وسائط المنصة — `<video>` و`<audio>`
     ================================================================== */
  function mountNative(el, opt, bus, audio) {
    el.innerHTML = '';
    var m = document.createElement(audio ? 'audio' : 'video');
    m.controls = true;
    m.preload = 'metadata';
    m.playsInline = true;
    m.setAttribute('playsinline', '');
    m.src = opt.url;
    if (opt.title) m.setAttribute('title', opt.title);
    el.appendChild(m);

    if (opt.startAt > 0) {
      /* الموضع يكتب بعد أن تعرف المدة: كتابته قبلها تسقط بلا خطأ. */
      m.addEventListener('loadedmetadata', function () {
        try { m.currentTime = opt.startAt; } catch (e) {}
      }, { once: true });
    }

    m.addEventListener('play',       function () { bus.fire('play'); });
    m.addEventListener('pause',      function () { bus.fire('pause'); });
    m.addEventListener('ended',      function () { bus.fire('pause'); bus.fire('ended'); });
    m.addEventListener('timeupdate', function () { bus.fire('time', m.currentTime); });
    m.addEventListener('loadedmetadata', function () { bus.fire('ready'); });
    announceDuration(bus, function () {
      return isFinite(m.duration) ? (m.duration || 0) : 0;
    });

    return Promise.resolve({
      kind: 'native',
      provider: audio ? 'audio' : 'html5',
      el: m,
      duration: function () { return isFinite(m.duration) ? (m.duration || 0) : 0; },
      currentTime: function () { return m.currentTime || 0; },
      seek: function (s) { try { m.currentTime = s; } catch (e) {} },
      canRate: function () { return true; },
      setRate: function (r) { m.playbackRate = parseFloat(r); return true; },
      pause: function () { try { m.pause(); } catch (e) {} },
      destroy: function () { try { m.pause(); m.removeAttribute('src'); m.load(); } catch (e) {} }
    });
  }

  /* ==================================================================
     ما لا يعلن موضعه — درايف وإطار خارجي
     ==================================================================
     يعرض الإطار كما هو، ويعلن `kind: 'none'` صراحة. ولا يخترع موضعا:
     شريط تقدم يتحرك بمؤقت على شيء قد يكون الطالب أوقفه كذب أسوأ من
     الصمت — والشاشة تعرض «أنهيت الدرس» بدلا منه.
  */
  function mountEmbed(el, opt, bus, drive) {
    var src = opt.url;
    if (drive) {
      var id = driveId(opt.url);
      if (id) src = 'https://drive.google.com/file/d/' + id + '/preview';
    }

    el.innerHTML = '';
    /* الوسم الكامل يقبل كما هو حين يلصقه صاحبه: بعض الأدوات لا تعمل
       إلا بسماتها التي كتبتها. وما ليس وسما يلف بإطار. */
    if (/^\s*<iframe/i.test(src)) {
      el.innerHTML = src;
    } else {
      var f = document.createElement('iframe');
      f.src = src;
      f.loading = 'lazy';
      f.allowFullscreen = true;
      f.setAttribute('allow', 'autoplay; fullscreen; picture-in-picture');
      if (opt.title) f.title = opt.title;
      el.appendChild(f);
    }

    setTimeout(function () { bus.fire('ready'); }, 0);

    return Promise.resolve({
      kind: 'none',
      provider: drive ? 'google_drive' : 'embed',
      duration: function () { return 0; },
      currentTime: function () { return 0; },
      seek: function () {},
      canRate: function () { return false; },
      setRate: function () { return false; },
      pause: function () {},
      destroy: function () { el.innerHTML = ''; }
    });
  }

  /* ================================================================== */

  var TQPlayer = {
    detect: detect,

    /**
     * يركب المشغل المناسب في العنصر.
     *
     * والفشل يرد مشغلا من نوع `none` لا استثناء يوقف الصفحة: سكربت
     * يوتيوب قد يحجب في شبكة مدرسة، وحينها يبقى الدرس معروضا ويقال
     * للطالب إن الإتمام بيده — لا شاشة بيضاء.
     */
    mount: function (el, opt) {
      opt = opt || {};
      var bus  = emitter();
      var kind = detect(opt.type, opt.url);

      var p;
      if (kind === 'youtube')      p = mountYouTube(el, opt, bus);
      else if (kind === 'vimeo')   p = mountVimeo(el, opt, bus);
      else if (kind === 'html5')   p = mountNative(el, opt, bus, false);
      else if (kind === 'audio')   p = mountNative(el, opt, bus, true);
      else if (kind === 'drive')   p = mountEmbed(el, opt, bus, true);
      else                         p = mountEmbed(el, opt, bus, false);

      return p.then(function (player) {
        player.on = bus.on;
        return player;
      }).catch(function (err) {
        if (global.console && console.warn) console.warn('TQPlayer:', err && err.message);
        return mountEmbed(el, opt, bus, kind === 'drive').then(function (player) {
          player.on = bus.on;
          player.degraded = true;
          return player;
        });
      });
    }
  };

  global.TQPlayer = TQPlayer;
})(window);
