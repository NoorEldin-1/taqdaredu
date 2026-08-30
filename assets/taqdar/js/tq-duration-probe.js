/**
 * قارئ المدة — TQ-PROBE.
 *
 * ═══ لماذا وجد ═══
 *
 * `lesson_types()` تعلن `'probe' => true` على يوتيوب وفيميو منذ كتبت،
 * و`tq_cur_field()` تطبع `data-tq-probe="1"` ولوحا فارغا ينتظر النتيجة،
 * والوسم تحت الحقل يعد صراحة: «تقرأ تلقائيا من الرابط، وتكتب بيد إن
 * تعذر». ولم يكن في المستودع **سطر جافاسكربت واحد** يقرأ أيا من ذلك.
 *
 * فالمعلم يكتب المدة بيده حزرا. وهي ليست حقلا تجميليا: `duration_sec`
 * هو مقام نسبة التقدم ومنه يشتق عدد دلاء التغطية، وعليه يفتح الدرس
 * التالي. فمن كتب `00:01:00` على مقطع طوله `00:02:49` جعل درسه يعد
 * مكتملا بعد ثلث مشاهدته — ومن كتب `00:12:00` على مقطع طوله دقيقتان
 * أقفل مقرره على كل من اشترك. والخطأ يظهر عند الطالب لا عند من كتبه.
 *
 * ═══ لماذا في المتصفح لا في الخادم ═══
 *
 * يوتيوب لا يعلن مدته إلا لمشغله، وقراءتها في الخادم تحتاج مفتاح
 * YouTube Data API — مفتاحا ثالثا يضبط ويحد ويسرب، لخانة واحدة في
 * نموذج. والمشغل موجود عندنا أصلا.
 *
 * ═══ ولا مشغل ثانيا ═══
 *
 * هذا الملف **لا يفكك رابطا ولا يبني إطارا**: ينادي `TQPlayer.mount()`
 * نفسه الذي يشغل الدرس عند الطالب، في حاوية مخفية، وينصت لحدث
 * `duration`. فما يقرؤه المعلم هنا هو ما سيقيسه الطالب هناك بالضبط —
 * ونسخة ثانية من منطق التعرف كانت ستفترق عن أختها عند أول مصدر جديد.
 *
 * وحدث `duration` **لاصق** (TQ-READY-LOST)، فالمستمع الذي يسجل بعد
 * وقوعه يسمعه. وبلا ذلك لكان هذا الملف يقرأ صفرا على أكثر الروابط.
 *
 * ═══ القاعدة الحاكمة ═══
 *
 * **القراءة اقتراح لا فرض.** تملأ الحقل الفارغ وحده. وما كتبه المعلم
 * بيده لا يمحى — يعرض بجواره «المقاس: 00:02:49 · استعمله» فيقرر هو.
 * ومن أخطأ في اللصق ثم صحح لا يفقد ما كتب مرتين.
 */
(function (global, doc) {
  'use strict';

  /* نافذة الدرس في اللوحة تحقن بـjQuery، وهي **تنفذ** وسوم `<script>`
     التي تجدها — في كل فتحة. فبلا هذا الحارس يولد مراقب تغير جديد مع
     كل فتح، ويبقى كلهم يعملون. */
  if (global.TQDurationProbe) return;

  var READY_MS   = 32000;  /* سقف انتظار المصدر البعيد — فوق نافذة `announceDuration` (٣٠ ثانية) بقليل */
  var DEBOUNCE   = 600;    /* بين آخر حرف يلصق والسؤال */
  var HOST_ID    = 'tq-probe-host';

  /* ---- أدوات صغيرة ------------------------------------------------ */

  function hms(sec) {
    sec = Math.max(0, Math.round(sec || 0));
    var h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60;
    function p(n) { return (n < 10 ? '0' : '') + n; }
    return p(h) + ':' + p(m) + ':' + p(s);
  }

  /* الأرقام تعرض بالعزل ثنائي الاتجاه: `00:02:49` داخل جملة عربية
     ينقلب إلى `49:02:00` بلا هذا. */
  function iso(txt) { return '⁦' + txt + '⁩'; }

  /** حاوية مخفية خارج الشاشة — لا `display:none`: إطار يوتيوب لا يحمل
      وسائطه فيها، فلا يعلن مدة أبدا. */
  function host() {
    var el = doc.getElementById(HOST_ID);
    if (el) return el;
    el = doc.createElement('div');
    el.id = HOST_ID;
    el.setAttribute('aria-hidden', 'true');
    el.style.cssText = 'position:absolute;inset-inline-start:-99999px;top:0;'
                     + 'inline-size:320px;block-size:180px;overflow:hidden;'
                     + 'opacity:0;pointer-events:none';
    doc.body.appendChild(el);
    return el;
  }

  /* ---- اللوح الذي يقرأ المعلم ------------------------------------- */

  function Panel(out) {
    this.el = out;
  }
  Panel.prototype.hide = function () {
    if (!this.el) return;
    this.el.hidden = true;
    this.el.textContent = '';
  };
  Panel.prototype.say = function (text) {
    if (!this.el) return;
    this.el.hidden = false;
    this.el.textContent = text;
  };
  /**
   * النتيجة ومعها زر يستعملها.
   *
   * الزر `type="button"` صراحة: الزر بلا نوع داخل `<form>` **يرسل
   * النموذج** — فمن ضغط «استعمله» كان يحفظ الدرس ناقصا.
   */
  Panel.prototype.offer = function (sec, onUse) {
    if (!this.el) return;
    this.el.hidden = false;
    this.el.textContent = TQ.t('المقاس من المصدر: ____ — ', iso(hms(sec)));
    var b = doc.createElement('button');
    b.type = 'button';
    b.className = 'tq-linkish';
    b.textContent = TQ.t('استعمله');
    b.style.cssText = 'background:none;border:0;padding:0;font:inherit;'
                    + 'color:var(--tq-navy,#0b3b36);text-decoration:underline;cursor:pointer';
    b.addEventListener('click', function () { onUse(sec); });
    this.el.appendChild(b);
  };

  /* ---- القياس ----------------------------------------------------- */

  /**
   * يسأل المصدر عن مدته.
   *
   * @param {string} url    الرابط، أو رابط كائن لملف اختير
   * @param {string} kind   نوع المصدر كما تسميه `lesson_types()`
   * @param {function} done (sec|0, reason)
   * @returns {function} يلغي القياس الجاري
   */
  function measure(url, kind, done) {
    var settled = false, player = null, timer = null;

    function finish(sec, why) {
      if (settled) return;
      settled = true;
      if (timer) clearTimeout(timer);
      try { if (player && player.destroy) player.destroy(); } catch (e) {}
      try { host().innerHTML = ''; } catch (e) {}
      done(sec || 0, why || '');
    }

    if (!global.TQPlayer || !global.TQPlayer.mount) {
      finish(0, TQ.t('المشغل لم يحمل في هذه الصفحة.'));
      return function () {};
    }

    var box = doc.createElement('div');
    box.style.cssText = 'inline-size:320px;block-size:180px';
    host().innerHTML = '';
    host().appendChild(box);

    timer = setTimeout(function () {
      finish(0, TQ.t('لم يرد المصدر بمدته. اكتبها بيدك.'));
    }, READY_MS);

    try {
      global.TQPlayer.mount(box, { url: url, type: kind, muted: true, autoplay: false })
        .then(function (p) {
          player = p;
          /* `duration` لاصق: يسمعه من سجل بعد وقوعه — وهو الشرط الذي
             بدونه لا يعمل هذا الملف على يوتيوب أصلا (TQ-READY-LOST). */
          p.on('duration', function (d) {
            if (d > 0) finish(d, '');
          });
          /* والمصدر الذي لا يعلن موضعه لا يعلن مدة: يقال ذلك فورا
             بدل انتظار نصف دقيقة على لا شيء.
             و`degraded` تفرق بين حالين يقول لهما `kind: 'none'` نفسه:
             نوع لا يقاس أصلا (درايف، إطار خارجي) — وهذا خبر لا عطل؛
             ونوع يقاس سقط مشغله إلى الإطار العاري لأن الرابط لا يفكك
             أو السكربت حجب — وهذا عطل يصلح. */
          if (p.kind === 'none') {
            finish(0, p.degraded
              ? TQ.t('تعذر فتح المصدر بمشغله — تحقق من الرابط، أو اكتب المدة بيدك.')
              : TQ.t('هذا المصدر لا يعلن مدته — اكتبها بيدك.'));
          }
        })
        .catch(function () {
          finish(0, TQ.t('تعذر فتح المصدر. تحقق من الرابط.'));
        });
    } catch (e) {
      finish(0, TQ.t('تعذر فتح المصدر.'));
    }

    return function () { finish(0, ''); };
  }

  /* ---- ربط حقل برابطه ---------------------------------------------- */

  /** حقل المدة الذي يخص هذا الرابط: من لوح نوعه، لا من الصفحة كلها. */
  function durationFor(input) {
    var scope = input.closest('[data-tqc-pane]')
             || input.closest('fieldset')
             || input.closest('form')
             || doc;
    return scope.querySelector('[data-tq-cur="duration"]');
  }

  /** لوح النتيجة الذي يخص هذا الرابط. */
  function outFor(input) {
    var scope = input.closest('[data-tqc-pane]') || input.parentNode;
    return scope ? scope.querySelector('[data-tq-probe-out]') : null;
  }

  /**
   * نوع المشغل — من `data-tq-probe` نفسه.
   *
   * تكتبه `lesson_types()` في الوصف (`'probe' => 'youtube'`) وتطبعه
   * `tq_cur_field()`. فالنوع يأتي من الوصف الواحد لا يحزر من الرابط،
   * ونوع جديد يضاف هناك وحده فيعمل هنا بلا تعديل.
   */
  function kindFor(input) {
    return input.getAttribute('data-tq-probe') || '';
  }

  /** هل كتب المعلم مدة فعلا؟ `00:00:00` ليست مدة. */
  function hasValue(el) {
    if (!el) return false;
    var v = String(el.value || '').trim();
    return v !== '' && !/^0{1,3}(:0{1,2}){1,2}$/.test(v);
  }

  function bindUrl(input) {
    var timer = null, cancel = null, last = '';

    function run() {
      var url = String(input.value || '').trim();
      if (url === last) return;
      last = url;

      if (cancel) { cancel(); cancel = null; }

      var out  = new Panel(outFor(input));
      var dur  = durationFor(input);
      if (!url) { out.hide(); return; }
      if (!/^https?:\/\//i.test(url)) { out.hide(); return; }

      out.say(TQ.t('يقرأ مدة المقطع…'));

      cancel = measure(url, kindFor(input), function (sec, why) {
        if (!sec) { if (why) out.say(why); else out.hide(); return; }

        if (!hasValue(dur)) {
          dur.value = hms(sec);
          /* الحدث يطلق يدويا: من يستمع إلى الحقل (تحقق، أو حفظ مسودة)
             لا يسمع الكتابة بالبرمجة. */
          dur.dispatchEvent(new Event('input',  { bubbles: true }));
          dur.dispatchEvent(new Event('change', { bubbles: true }));
          out.say(TQ.t('قرئت المدة من المصدر: ____', iso(hms(sec))));
          return;
        }

        /* مكتوب بيد ويخالف المقاس: يعرض ولا يكتب — TQ-DURATION.
           والفرق اليسير لا يقال: ثانية أو ثانيتان من تقريب المصدر. */
        var typed = parse(dur.value);
        if (Math.abs(typed - sec) <= Math.max(3, sec * 0.02)) { out.hide(); return; }

        out.offer(sec, function (s) {
          dur.value = hms(s);
          dur.dispatchEvent(new Event('input',  { bubbles: true }));
          dur.dispatchEvent(new Event('change', { bubbles: true }));
          out.say(TQ.t('كتبت المدة المقاسة: ____', iso(hms(s))));
        });
      });
    }

    function schedule() {
      if (timer) clearTimeout(timer);
      timer = setTimeout(run, DEBOUNCE);
    }

    input.addEventListener('input', schedule);
    input.addEventListener('change', run);
    input.addEventListener('paste', function () { setTimeout(run, 50); });
    input.addEventListener('blur', run);

    /* رابط محفوظ من قبل ومدته فارغة: يقرأ عند فتح الشاشة. ولا يقرأ
       متى كانت المدة مكتوبة — لا نشغل مصدرا بعيدا بلا حاجة. */
    if (String(input.value || '').trim() !== '' && !hasValue(durationFor(input))) {
      schedule();
    }
  }

  /** `hh:mm:ss` أو `mm:ss` إلى ثوان. */
  function parse(txt) {
    var p = String(txt || '').trim().split(':').map(Number);
    if (p.some(isNaN)) return 0;
    if (p.length === 3) return p[0] * 3600 + p[1] * 60 + p[2];
    if (p.length === 2) return p[0] * 60 + p[1];
    return p[0] || 0;
  }

  /* ---- الملف المرفوع: يقرأ قبل أن يرفع ----------------------------- */

  /**
   * ملف الوسائط يعلن مدته في المتصفح **قبل** أن يصعد إلى الخادم:
   * `createObjectURL` يعطيه رابطا محليا، وعنصر الوسائط يقرأ ترويسته.
   * فلا انتظار رفع، ولا رحلة ذهاب وإياب.
   */
  function bindFile(input) {
    input.addEventListener('change', function () {
      var f = input.files && input.files[0];
      var dur = durationFor(input);
      var out = new Panel(outFor(input));
      if (!f || !dur) return;

      var isAudio = /^audio\//.test(f.type) || /\.(mp3|m4a|wav|ogg|oga|aac)$/i.test(f.name);
      var el  = doc.createElement(isAudio ? 'audio' : 'video');
      var url = URL.createObjectURL(f);
      var done = false;

      out.say(TQ.t('يقرأ مدة الملف…'));

      function finish(sec, why) {
        if (done) return;
        done = true;
        try { URL.revokeObjectURL(url); } catch (e) {}
        if (!sec) { out.say(why || TQ.t('تعذر قراءة مدة هذا الملف. اكتبها بيدك.')); return; }
        if (!hasValue(dur)) {
          dur.value = hms(sec);
          dur.dispatchEvent(new Event('input',  { bubbles: true }));
          dur.dispatchEvent(new Event('change', { bubbles: true }));
          out.say(TQ.t('قرئت المدة من الملف: ____', iso(hms(sec))));
        } else {
          out.offer(sec, function (s) {
            dur.value = hms(s);
            dur.dispatchEvent(new Event('input',  { bubbles: true }));
            dur.dispatchEvent(new Event('change', { bubbles: true }));
            out.say(TQ.t('كتبت المدة المقاسة: ____', iso(hms(s))));
          });
        }
      }

      el.preload = 'metadata';
      el.addEventListener('loadedmetadata', function () {
        finish(isFinite(el.duration) ? Math.round(el.duration) : 0, '');
      });
      el.addEventListener('error', function () { finish(0, ''); });
      setTimeout(function () { finish(0, ''); }, 15000);
      el.src = url;
    });
  }

  /* ---- الإقلاع ----------------------------------------------------- */

  function scan(root) {
    (root || doc).querySelectorAll('[data-tq-probe]').forEach(function (el) {
      if (el.__tqProbe) return;
      el.__tqProbe = true;
      if (el.type === 'file') bindFile(el); else bindUrl(el);
    });
  }

  function boot() {
    scan(doc);
    /* النافذة تبنى مرة وتعرض مرارا، وشاشة المنهج تحقن ألواح الأنواع
       عند فتحها. فالمسح يعاد على ما يضاف — بلا تكرار الربط. */
    if (global.MutationObserver) {
      new MutationObserver(function (recs) {
        for (var i = 0; i < recs.length; i++) {
          if (recs[i].addedNodes && recs[i].addedNodes.length) { scan(doc); return; }
        }
      }).observe(doc.body, { childList: true, subtree: true });
    }
  }

  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', boot);
  else boot();

  global.TQDurationProbe = { scan: scan, measure: measure, hms: hms };

})(window, document);
