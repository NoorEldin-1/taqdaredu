/* منصّة تقدّر — سلوك الواجهة. بلا اعتماد على أي مكتبة. */
(function () {
  'use strict';

  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  /* الوضع الليليّ أُزيل: الوجه واحد فاتح. ولا يُكتب تفضيلٌ
     على الجهاز، فلم يبقَ من التخزين إلّا سجلّ اختيار الكوكيز. */

  /* ---- درج القائمة على الجوّال --------------------------------------- */
  var rail = $('[data-tq-rail]');
  var scrim = $('[data-tq-scrim]');
  function closeRail() {
    if (!rail) return;
    rail.removeAttribute('data-open');
    if (scrim) { scrim.removeAttribute('data-open'); scrim.hidden = true; }
    $$('[data-tq-rail-toggle]').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
  }
  $$('[data-tq-rail-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (!rail) return;
      var open = rail.getAttribute('data-open') === 'true';
      if (open) { closeRail(); return; }
      rail.setAttribute('data-open', 'true');
      if (scrim) { scrim.hidden = false; requestAnimationFrame(function () { scrim.setAttribute('data-open', 'true'); }); }
      b.setAttribute('aria-expanded', 'true');
    });
  });
  if (scrim) scrim.addEventListener('click', closeRail);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeRail(); });

  /* ---- ⌘K / Ctrl+K يفتح البحث ---------------------------------------- */
  document.addEventListener('keydown', function (e) {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
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

  /* ---- حالة «بلا اتصال» ---------------------------------------------- */
  function net() {
    var bar = $('[data-tq-offline]');
    if (bar) bar.hidden = navigator.onLine;
  }
  addEventListener('online', net); addEventListener('offline', net); net();

  /* ---- حلقات ومؤشّرات التقدّم تملأ عند الظهور لا عند التحميل ---------- */
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
