/**
 * TQ-COUPON — «طبق» بلا إعادة تحميل.
 *
 * تحسين لا شرط: الزر زر إرسال يعمل بلا هذا الملف (يعيد الخادم بناء
 * الشاشة والخصم مطبوع). وهذا الملف **لا يحسب رقما**: يسأل الخادم
 * (`coupon/check`) ويضع ما رده حيث يطبع الإجمالي — فلا يفترق رقم الشاشة
 * عن رقم الفاتورة لأن قاعدة كتبت هنا مرة وهناك مرة.
 */
(function () {
  'use strict';
  if (!window.fetch || !window.FormData) return;

  function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  /* بوابة ولي الأمر: العنصر والابن والدورة هي **المختارة الآن** في نموذج
     الحقل — لا رقم ثابت، فالأب يبدل الباقة بعد أن يكتب الكود. */
  function picked(box, attr) {
    var name = box.getAttribute(attr);
    var form = box.closest('form');
    if (!name || !form) return '';
    var el = form.querySelector('[name="' + name + '"]:checked') || form.querySelector('[name="' + name + '"]');
    return el ? el.value : '';
  }

  function paint(box, r) {
    var ok = !!r.applied;

    /* البوابة: لا إجمالي في الشاشة يبدل — الرسالة تقول الرقمين. */
    if (box.hasAttribute('data-portal')) {
      var pm = box.querySelector('[data-tq-coupon-msg]');
      if (pm) {
        pm.textContent = (r.message || '') + (ok && r.pay_line ? ' — ' + r.pay_line : '');
        pm.classList.toggle('is-ok', ok);
        pm.classList.toggle('is-err', !ok && !!r.message);
      }
      return;
    }
    $all('[data-tq-coupon-net]').forEach(function (n) { n.innerHTML = r.net_html; });
    $all('[data-tq-coupon-sar]').forEach(function (n) { n.textContent = r.net_sar; });
    $all('[data-tq-coupon-was]').forEach(function (n) { n.hidden = !ok; });
    $all('[data-tq-coupon-row]').forEach(function (n) { n.hidden = !ok; });
    $all('[data-tq-coupon-disc]').forEach(function (n) { n.innerHTML = r.disc_html; });
    $all('[data-tq-coupon-code]').forEach(function (n) { n.textContent = r.code || ''; });

    var msg = box.querySelector('[data-tq-coupon-msg]');
    if (msg) {
      msg.textContent = r.message || '';
      msg.classList.toggle('is-ok', ok);
      msg.classList.toggle('is-err', !ok && !!r.message);
    }
    var head = box.querySelector('[data-tq-coupon-head]');
    if (head) {
      head.textContent = ok ? head.getAttribute('data-on').replace('%s', r.code) : head.getAttribute('data-off');
    }
    var clr = box.querySelector('[data-tq-coupon-clear]');
    if (clr) clr.hidden = !ok;
    var inp = box.querySelector('[data-tq-coupon-in]');
    if (inp && ok) inp.value = r.code;

    /* من يعرض المبلغ خارج هذه الأوسمة (زر الدفع السريع) يسمع هنا: الصافي
       بالهللات كما رده الخادم، لا رقما يحسبه بنفسه. */
    try {
      document.dispatchEvent(new CustomEvent('tq:coupon', { detail: {
        applied: ok, code: ok ? r.code : '', net: r.net, gross: r.gross, discount: r.discount
      } }));
    } catch (e) { /* متصفح قديم بلا CustomEvent — لا أحد يسمع */ }
  }

  function ask(box, clear) {
    var inp = box.querySelector('[data-tq-coupon-in]');
    var go  = box.querySelector('[data-tq-coupon-go]');
    var fd  = new FormData();
    fd.append('coupon', inp ? inp.value : '');
    fd.append('kind', box.getAttribute('data-kind'));
    fd.append('item_id', box.getAttribute('data-item') || picked(box, 'data-item-from'));
    fd.append('cycle', box.getAttribute('data-cycle') || picked(box, 'data-cycle-from'));
    fd.append('child_id', picked(box, 'data-child-from'));
    if (clear) fd.append('clear', '1');
    var cn = box.getAttribute('data-csrf-name');
    if (cn) fd.append(cn, box.getAttribute('data-csrf') || '');

    var msg = box.querySelector('[data-tq-coupon-msg]');
    if (!clear && msg) { msg.textContent = box.getAttribute('data-busy') || '…'; msg.classList.remove('is-ok', 'is-err'); }
    if (go) go.disabled = true;

    return fetch(box.getAttribute('data-url'), {
      method: 'POST', body: fd, credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    }).then(function (res) {
      if (!res.ok) throw new Error('http ' + res.status);
      return res.json();
    }).then(function (r) {
      paint(box, r);
    }).catch(function () {
      /* تعذر الطلب: يرد إلى الإرسال العادي — الخادم يحكم ويعيد الشاشة. */
      if (msg) msg.textContent = '';
      if (go && go.form) {
        box.setAttribute('data-native', '1');
        go.disabled = false;
        go.click();
      }
    }).then(function () {
      if (go) go.disabled = false;
    });
  }

  $all('[data-tq-coupon]').forEach(function (box) {
    var go  = box.querySelector('[data-tq-coupon-go]');
    var clr = box.querySelector('[data-tq-coupon-clear]');
    var inp = box.querySelector('[data-tq-coupon-in]');
    var busy = false;

    function run(e, clear) {
      if (box.getAttribute('data-native') === '1') return;   // الإرسال العادي
      if (busy) { if (e) e.preventDefault(); return; }
      if (e) e.preventDefault();
      if (!clear && inp && inp.value.trim() === '') { inp.focus(); return; }
      busy = true;
      ask(box, clear).then(function () {
        busy = false;
        if (clear && inp) { inp.value = ''; inp.focus(); }
      });
    }

    if (go)  go.addEventListener('click', function (e) { run(e, false); });
    /* البوابة: تبديل الباقة أو الابن بعد التحقق يجعل الحكم القديم كاذبا —
       يعاد التحقق على المختار الجديد متى كان في الحقل كود. */
    if (box.hasAttribute('data-portal') && box.closest('form')) {
      box.closest('form').addEventListener('change', function (e) {
        var n = e.target && e.target.name;
        if (inp && inp.value.trim() !== '' && n && n !== 'method' && n !== 'coupon') run(null, false);
      });
    }
    if (clr) clr.addEventListener('click', function (e) { run(e, true); });
    /* «إدخال» في الحقل يطبق الكود ولا يرسل الشراء: الحقل داخل نموذج
       الشراء، والإرسال الضمني يأخذ أول زر فيه. */
    if (inp) inp.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') run(e, false);
    });
  });
})();
