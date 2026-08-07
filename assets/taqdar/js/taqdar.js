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
      var label = on ? 'توسيع القائمة الجانبية' : 'طي القائمة الجانبية';
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
