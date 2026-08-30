/* منصة تقدر — سلوك الواجهة. بلا اعتماد على أي مكتبة. */
(function () {
  'use strict';

  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  /* الوضع الليلي أزيل: الوجه واحد فاتح. ولا يكتب تفضيل
     على الجهاز، فلم يبق من التخزين إلا سجل اختيار الكوكيز. */

  /* ---- درج القائمة على الجوال ---------------------------------------
     الدرج يغطي الشاشة كلها، فما دام مفتوحا: الصفحة تحته لا تمرر، والتركيز
     ينتقل إليه ويعود إلى الزر الذي فتحه. درج يفتح ويترك التركيز خلفه يجعل
     التنقل بالمفاتيح يمر على روابط لا تراها العين. */
  var rail = $('[data-tq-rail]');
  var scrim = $('[data-tq-scrim]');
  var railOpener = null;

  function railIsOpen() { return !!rail && rail.getAttribute('data-open') === 'true'; }

  function closeRail() {
    if (!rail) return;
    var was = railIsOpen();
    rail.removeAttribute('data-open');
    if (scrim) { scrim.removeAttribute('data-open'); scrim.hidden = true; }
    $$('[data-tq-rail-toggle]').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
    document.body.style.removeProperty('overflow');
    if (was && railOpener && railOpener.focus) railOpener.focus();
    railOpener = null;
  }

  function openRail(btn) {
    if (!rail) return;
    railOpener = btn || null;
    rail.setAttribute('data-open', 'true');
    if (scrim) { scrim.hidden = false; requestAnimationFrame(function () { scrim.setAttribute('data-open', 'true'); }); }
    $$('[data-tq-rail-toggle]').forEach(function (b) { b.setAttribute('aria-expanded', 'true'); });
    document.body.style.overflow = 'hidden';
    var first = rail.querySelector('.tq-rail__close, .tq-rail__item');
    if (first && first.focus) first.focus();
  }

  $$('[data-tq-rail-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      railIsOpen() ? closeRail() : openRail(b);
    });
  });
  if (scrim) scrim.addEventListener('click', closeRail);

  /* التنقل داخل الدرج يغلقه: الرابط الذي يشير إلى موضع في الصفحة نفسها
     لا يعيد التحميل، فيبقى الدرج مفتوحا فوق ما ذهب إليه المستخدم. */
  if (rail) rail.addEventListener('click', function (e) {
    if (railIsOpen() && e.target.closest('.tq-rail__item')) closeRail();
  });

  /* ---- طي الشريط على الشاشات الكبيرة ---------------------------------
     الحالة على `<html>` لا على الشريط، وتقرأ في الرأس قبل الرسم
     (انظر includes_top.php) فلا تومض الصفحة مفتوحة ثم تنطوي. */
  function railCollapsed() {
    return document.documentElement.getAttribute('data-tq-rail') === 'collapsed';
  }
  function syncCollapseBtn() {
    var on = railCollapsed();
    $$('[data-tq-rail-collapse]').forEach(function (b) {
      b.setAttribute('aria-expanded', on ? 'false' : 'true');
      var label = on ? TQ.t('توسيع القائمة الجانبية') : TQ.t('طي القائمة الجانبية');
      b.setAttribute('aria-label', label);
      b.setAttribute('title', label);
    });
  }
  $$('[data-tq-rail-collapse]').forEach(function (b) {
    b.addEventListener('click', function () {
      var next = railCollapsed() ? '' : 'collapsed';
      if (next) document.documentElement.setAttribute('data-tq-rail', next);
      else document.documentElement.removeAttribute('data-tq-rail');
      try { localStorage.setItem('tq-rail', next || 'open'); } catch (e) {}
      syncCollapseBtn();
    });
  });
  syncCollapseBtn();

  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeRail(); });

  /* ---- ⌘K / Ctrl+K يفتح البحث ---------------------------------------- */
  /* الشريحة تكتب باسم المنصة التي تقرأ عليها: «⌘K» على أجهزة أبل وحدها،
     و«Ctrl K» على ما سواها — ووعد باختصار لا يعمل أسوأ من لا وعد. */
  var isApple = /Mac|iPhone|iPad|iPod/i.test(navigator.platform || navigator.userAgent || '');
  if (!isApple) {
    $$('[data-tq-kbd]').forEach(function (k) { k.innerHTML = '<span class="tq-ltr">Ctrl K</span>'; });
  }
  document.addEventListener('keydown', function (e) {
    if ((e.metaKey || e.ctrlKey) && String(e.key || '').toLowerCase() === 'k') {
      var q = $('[data-tq-search]');
      if (q) { e.preventDefault(); q.focus(); q.select(); }
    }
  });

  /* ---- التبويبات ------------------------------------------------------ */
  $$('[data-tq-tabs]').forEach(function (group) {
    var tabs = $$('[role="tab"]', group);
    tabs.forEach(function (tab, i) {
      tab.addEventListener('click', function () { select(i); });
      tab.addEventListener('keydown', function (e) {
        var n = e.key === 'ArrowLeft' ? i + 1 : e.key === 'ArrowRight' ? i - 1 : -1;
        if (n < 0 || n >= tabs.length) return;
        e.preventDefault(); select(n); tabs[n].focus();
      });
    });
    function select(i) {
      tabs.forEach(function (t, j) {
        t.setAttribute('aria-selected', j === i ? 'true' : 'false');
        t.tabIndex = j === i ? 0 : -1;
        var panel = document.getElementById(t.getAttribute('aria-controls') || '');
        if (panel) panel.hidden = j !== i;
      });
    }
  });

  /* ---- شريط ملفات الارتباط ------------------------------------------- */
  var cookie = $('[data-tq-cookie]');
  if (cookie) {
    var seen;
    try { seen = localStorage.getItem('tq-cookie'); } catch (e) {}
    if (!seen) cookie.hidden = false;

    function decide(v) {
      cookie.hidden = true;
      try {
        localStorage.setItem('tq-cookie', v);
      } catch (e) {}
    }
    var ok = $('[data-tq-cookie-accept]', cookie);
    if (ok) ok.addEventListener('click', function () { decide('accepted'); });
    var no = $('[data-tq-cookie-deny]', cookie);
    if (no) no.addEventListener('click', function () { decide('denied'); });
  }

  /* ---- نافذة التأكيد --------------------------------------------------
     تحل محل `window.confirm()`. ذاك صندوق يرسمه المتصفح: عنوانه اسم
     المضيف («localhost:8081 says»)، وزراه «OK» و«Cancel» بالإنجليزية،
     وخطه خط النظام لا خط المنصة — يظهر فوق واجهة عربية فيقطعها، ولا
     يفرق بين «سحب طلب» و«إلغاء ربط» في الشكل ولا في اللون.

     الاستعمال: على النموذج (أو الرابط)
       data-tq-confirm="نص السؤال"
       data-tq-confirm-title="العنوان"          (اختياري)
       data-tq-confirm-ok="نص زر التأكيد"       (اختياري)
       data-tq-confirm-note="سطر تحت السؤال"    (اختياري)
       data-tq-confirm-tone="danger"            (اختياري)

     والإرسال بعد التأكيد يمر بـ`requestSubmit()` لا `submit()`: الثاني
     لا يطلق حدث `submit` أصلا، فيتخطى حاقن رمز الحماية في
     `includes_bottom.php` — فيصل الطلب بلا رمز ويرد الخادم 403. */
  var cf = null, cfPrev = null, cfTarget = null;

  function cfBuild() {
    if (cf) return cf;
    cf = document.createElement('div');
    cf.className = 'tq-confirm';
    cf.setAttribute('role', 'dialog');
    cf.setAttribute('aria-modal', 'true');
    cf.setAttribute('aria-labelledby', 'tq-confirm-t');
    cf.setAttribute('aria-describedby', 'tq-confirm-b');
    cf.hidden = true;
    cf.innerHTML =
      '<div class="tq-confirm__box" role="document">' +
        '<div class="tq-confirm__head">' +
          '<span class="tq-confirm__icon" aria-hidden="true">' +
            '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" ' +
            'stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' +
            '<path d="M12 8.5v5"/><path d="M12 16.5v.01"/><circle cx="12" cy="12" r="9"/></svg>' +
          '</span>' +
          '<div style="flex:1;min-inline-size:0">' +
            '<h2 class="tq-confirm__title" id="tq-confirm-t"></h2>' +
            '<p class="tq-confirm__body" id="tq-confirm-b"></p>' +
          '</div>' +
        '</div>' +
        '<p class="tq-confirm__note" data-note hidden></p>' +
        '<div class="tq-confirm__acts">' +
          '<button type="button" class="tq-btn tq-btn--ghost" data-cancel>' + TQ.t('إلغاء') + '</button>' +
          '<button type="button" class="tq-btn tq-btn--primary" data-ok></button>' +
        '</div>' +
      '</div>';
    document.body.appendChild(cf);

    cf.addEventListener('click', function (e) {
      if (e.target === cf) cfClose();               // النقر على الطبقة يغلق
    });
    cf.querySelector('[data-cancel]').addEventListener('click', cfClose);
    cf.querySelector('[data-ok]').addEventListener('click', cfAccept);

    /* حبس التركيز: نافذة تأخذ الشاشة ويهرب منها Tab إلى ما تحتها
       تجعل قارئ الشاشة يقرأ صفحة لا يراها المستخدم. */
    cf.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { e.preventDefault(); cfClose(); return; }
      if (e.key !== 'Tab') return;
      var f = $$('button, [href], input, select, textarea', cf)
                .filter(function (el) { return !el.disabled && el.offsetParent !== null; });
      if (!f.length) return;
      var first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
    return cf;
  }

  function cfClose() {
    if (!cf) return;
    cf.removeAttribute('data-open');
    cfTarget = null;
    document.body.style.removeProperty('overflow');
    setTimeout(function () { if (cf && !cf.hasAttribute('data-open')) cf.hidden = true; }, 220);
    if (cfPrev && cfPrev.focus) cfPrev.focus();
    cfPrev = null;
  }

  function cfAccept() {
    var t = cfTarget;
    cfClose();
    if (!t) return;
    t.setAttribute('data-tq-confirmed', '1');
    if (t.tagName === 'FORM') {
      if (t.requestSubmit) t.requestSubmit();
      else { t.removeAttribute('data-tq-confirm'); t.submit(); }
    } else {
      t.click();
    }
  }

  function cfOpen(el, opener) {
    var box = cfBuild();
    var tone = el.getAttribute('data-tq-confirm-tone') === 'danger' ? 'danger' : 'default';
    box.setAttribute('data-tone', tone);

    box.querySelector('#tq-confirm-t').textContent =
      el.getAttribute('data-tq-confirm-title') || TQ.t('تأكيد');
    box.querySelector('#tq-confirm-b').textContent = el.getAttribute('data-tq-confirm') || '';

    var note = box.querySelector('[data-note]');
    var noteText = el.getAttribute('data-tq-confirm-note') || '';
    note.textContent = noteText;
    note.hidden = noteText === '';

    var ok = box.querySelector('[data-ok]');
    ok.textContent = el.getAttribute('data-tq-confirm-ok') || TQ.t('تأكيد');
    ok.className = 'tq-btn ' + (tone === 'danger' ? 'tq-btn--danger' : 'tq-btn--primary');

    cfTarget = el;
    cfPrev = opener || document.activeElement;

    box.hidden = false;
    requestAnimationFrame(function () {
      box.setAttribute('data-open', 'true');
      document.body.style.overflow = 'hidden';
      /* التركيز على «إلغاء» لا على «تأكيد»: من ضغط Enter مسرعا يجب
         ألا يهدم شيئا، فالخيار الآمن هو المبدئي. */
      box.querySelector('[data-cancel]').focus();
    });
  }

  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f || f.tagName !== 'FORM' || !f.hasAttribute('data-tq-confirm')) return;
    if (f.getAttribute('data-tq-confirmed') === '1') {
      f.removeAttribute('data-tq-confirmed');       // مرة واحدة لكل تأكيد
      return;
    }
    e.preventDefault();
    cfOpen(f, document.activeElement);
  }, true);

  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[data-tq-confirm], button[data-tq-confirm]');
    if (!a || a.closest('form[data-tq-confirm]')) return;
    if (a.getAttribute('data-tq-confirmed') === '1') {
      a.removeAttribute('data-tq-confirmed');
      return;
    }
    e.preventDefault();
    cfOpen(a, a);
  }, true);

  /* ---- حالة «بلا اتصال» ---------------------------------------------- */
  function net() {
    var bar = $('[data-tq-offline]');
    if (bar) bar.hidden = navigator.onLine;
  }
  addEventListener('online', net); addEventListener('offline', net); net();

  /* ---- حلقات ومؤشرات التقدم تملأ عند الظهور لا عند التحميل ---------- */
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        var el = en.target;
        var v = el.getAttribute('data-tq-fill');
        if (el.classList.contains('tq-progress__fill')) el.style.inlineSize = v + '%';
        io.unobserve(el);
      });
    }, { threshold: 0.4 });
    $$('[data-tq-fill]').forEach(function (el) { io.observe(el); });
  } else {
    $$('[data-tq-fill]').forEach(function (el) { el.style.inlineSize = el.getAttribute('data-tq-fill') + '%'; });
  }
})();
