/**
 * TQ-CC-PICK · منتقي دولة الجوال — قائمة تقرأ لا قائمة نظام.
 *
 * منتقي النظام في حقل الجوال يعرض اثنتين وعشرين دولة سطرا واحدا ضيقا بخط
 * النظام، وعلى ويندوز يطبع العلم حرفين («EG») لأن النظام لا يرسم أعلام
 * الإيموجي. وهو آخر ما يلمسه المشتري قبل الدفع.
 *
 * **والمنتقي الأصلي يبقى هو الحقيقة**: هذا زر وقائمة فوقه، واختيار منها
 * يكتب `select.value` ويطلق `change` — فيسمعه `tq-phone.js` كما يسمعه من
 * النظام (مثال الحقل، ومسح الخطأ، وحفظ الدولة)، ويرسل النموذج ما كان
 * يرسله. وبلا هذا الملف يعمل الحقل كما كان حرفا بحرف.
 *
 * يركب على كل `[data-tq-cc-pick]` — يعلنه `tq_phone_field(..., ['picker'=>true])`.
 */
(function () {
  var boxes = document.querySelectorAll('[data-tq-cc-pick]');
  if (!boxes.length) return;

  /* أيرسم هذا الجهاز أعلام الإيموجي؟ علم يرسم لوحة ملونة، وما لا يرسم
     يخرج حرفين بلون واحد. فيرسم العلم في لوحة صغيرة ويعد ألوانه. */
  var flagsOk = (function () {
    try {
      var c = document.createElement('canvas');
      c.width = c.height = 20;
      var x = c.getContext('2d');
      x.textBaseline = 'top';
      x.font = '16px sans-serif';
      x.fillText('🇸🇦', 0, 0);           /* 🇸🇦 */
      var d = x.getImageData(0, 0, 20, 20).data, seen = {};
      for (var i = 0; i < d.length; i += 4) {
        if (d[i + 3] < 100) continue;
        seen[(d[i] >> 5) + '-' + (d[i + 1] >> 5) + '-' + (d[i + 2] >> 5)] = 1;
      }
      return Object.keys(seen).length > 3;
    } catch (e) { return false; }
  })();

  var uid = 0;

  function norm(s) {
    /* البحث يتسامح في الهمزات والتاء المربوطة: من كتب «الامارات» يقصد
       «الإمارات»، ومن كتب «مصر» أو «20» أو «+20» يجد مصر. */
    return String(s || '').toLowerCase()
      .replace(/[أإآ]/g, 'ا').replace(/ة/g, 'ه').replace(/ى/g, 'ي')
      .replace(/[\s+]/g, '');
  }

  Array.prototype.forEach.call(boxes, function (box) {
    var sel = box.querySelector('[data-tq-phone-cc]');
    var num = box.querySelector('[data-tq-phone-num]');
    if (!sel || box.getAttribute('data-tq-cc-ready')) return;
    box.setAttribute('data-tq-cc-ready', '1');
    var id = 'tqcc' + (++uid);

    var items = Array.prototype.map.call(sel.options, function (o) {
      var txt  = o.textContent || '';
      var flag = txt.split(' ')[0];
      return {
        iso:  o.value,
        dial: o.getAttribute('data-dial') || '',
        name: o.getAttribute('data-cname') || txt,
        flag: flag
      };
    });

    function mark(it, cls) {
      var m = document.createElement('span');
      m.className = cls + (flagsOk ? '' : ' is-code');
      m.setAttribute('aria-hidden', 'true');
      m.textContent = flagsOk ? it.flag : it.iso;
      return m;
    }

    /* ── الزر ───────────────────────────────────────────────────── */
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'cc-pick__btn';
    btn.setAttribute('aria-haspopup', 'listbox');
    btn.setAttribute('aria-expanded', 'false');
    btn.setAttribute('aria-controls', id + '-list');

    function paintBtn() {
      var it = items[sel.selectedIndex] || items[0];
      btn.textContent = '';
      btn.appendChild(mark(it, 'cc-pick__flag'));
      var d = document.createElement('span');
      d.className = 'cc-pick__dial';
      d.dir = 'ltr';
      d.textContent = '+' + it.dial;
      btn.appendChild(d);
      var c = document.createElement('span');
      c.className = 'cc-pick__caret';
      c.setAttribute('aria-hidden', 'true');
      btn.appendChild(c);
      btn.setAttribute('aria-label', 'رمز الدولة: ' + it.name + ' +' + it.dial + ' — اضغط للتغيير');
    }

    /* ── اللوح ──────────────────────────────────────────────────── */
    var pop = document.createElement('div');
    pop.className = 'cc-pick__pop';
    pop.hidden = true;

    var search = document.createElement('input');
    search.type = 'search';
    search.className = 'cc-pick__search';
    search.placeholder = 'ابحث باسم الدولة أو رمزها';
    search.setAttribute('aria-label', 'ابحث عن دولتك');
    search.setAttribute('aria-controls', id + '-list');
    search.setAttribute('autocomplete', 'off');
    search.setAttribute('role', 'combobox');
    search.setAttribute('aria-expanded', 'true');

    var list = document.createElement('ul');
    list.className = 'cc-pick__list';
    list.id = id + '-list';
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', 'الدول');

    var empty = document.createElement('p');
    empty.className = 'cc-pick__empty';
    empty.textContent = 'لا دولة بهذا الاسم.';
    empty.hidden = true;

    var rows = items.map(function (it, i) {
      var li = document.createElement('li');
      li.id = id + '-o' + i;
      li.className = 'cc-pick__opt';
      li.setAttribute('role', 'option');
      li.setAttribute('data-i', i);
      li.appendChild(mark(it, 'cc-pick__flag'));
      var n = document.createElement('span');
      n.className = 'cc-pick__name';
      n.textContent = it.name;
      li.appendChild(n);
      var d = document.createElement('span');
      d.className = 'cc-pick__code';
      d.dir = 'ltr';
      d.textContent = '+' + it.dial;
      li.appendChild(d);
      li._key = norm(it.name + ' ' + it.dial + ' ' + it.iso);
      list.appendChild(li);
      return li;
    });

    pop.appendChild(search);
    pop.appendChild(list);
    pop.appendChild(empty);

    sel.setAttribute('tabindex', '-1');
    sel.setAttribute('aria-hidden', 'true');
    sel.classList.add('cc-pick__native');
    box.classList.add('cc-pick');
    sel.parentNode.insertBefore(btn, sel);
    box.appendChild(pop);

    var active = -1;

    function visible() {
      return rows.filter(function (r) { return !r.hidden; });
    }
    function setActive(i) {
      rows.forEach(function (r) { r.classList.remove('is-active'); });
      active = i;
      if (i < 0) { search.removeAttribute('aria-activedescendant'); return; }
      rows[i].classList.add('is-active');
      search.setAttribute('aria-activedescendant', rows[i].id);
      var r = rows[i], top = r.offsetTop, bot = top + r.offsetHeight;
      if (top < list.scrollTop) list.scrollTop = top;
      else if (bot > list.scrollTop + list.clientHeight) list.scrollTop = bot - list.clientHeight;
    }
    function filter() {
      var q = norm(search.value);
      rows.forEach(function (r) { r.hidden = q !== '' && r._key.indexOf(q) === -1; });
      var v = visible();
      empty.hidden = v.length > 0;
      setActive(v.length ? rows.indexOf(v[0]) : -1);
    }

    function open() {
      if (!pop.hidden) return;
      pop.hidden = false;
      box.classList.add('is-open');
      btn.setAttribute('aria-expanded', 'true');
      rows.forEach(function (r, i) {
        r.setAttribute('aria-selected', String(i === sel.selectedIndex));
      });
      search.value = '';
      filter();
      setActive(sel.selectedIndex);
      /* على اللمس لا تفتح لوحة المفاتيح فوق القائمة: من يريد السعودية
         يراها أولا ويلمسها، ومن يريد غيرها يلمس البحث بنفسه. */
      if (!window.matchMedia || !window.matchMedia('(pointer:coarse)').matches) search.focus();
      document.addEventListener('mousedown', outside, true);
      document.addEventListener('touchstart', outside, true);
    }
    function close(back) {
      if (pop.hidden) return;
      pop.hidden = true;
      box.classList.remove('is-open');
      btn.setAttribute('aria-expanded', 'false');
      document.removeEventListener('mousedown', outside, true);
      document.removeEventListener('touchstart', outside, true);
      if (back) btn.focus();
    }
    function outside(e) {
      if (!box.contains(e.target)) close(false);
    }
    function choose(i) {
      if (i < 0) return;
      var changed = sel.selectedIndex !== i;
      sel.selectedIndex = i;
      paintBtn();
      close(false);
      if (changed) {
        var ev;
        try { ev = new Event('change', { bubbles: true }); }
        catch (e) { ev = document.createEvent('Event'); ev.initEvent('change', true, true); }
        sel.dispatchEvent(ev);                 /* `tq-phone.js` يركز الرقم */
      } else if (num) {
        num.focus();
      }
    }

    btn.addEventListener('click', function () { pop.hidden ? open() : close(true); });
    btn.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); open(); }
    });
    search.addEventListener('input', filter);
    search.addEventListener('keydown', function (e) {
      var v = visible(), at = v.indexOf(rows[active]);
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (v.length) setActive(rows.indexOf(v[Math.min(v.length - 1, at + 1)]));
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (v.length) setActive(rows.indexOf(v[Math.max(0, at - 1)]));
      } else if (e.key === 'Enter') {
        /* Enter هنا اختيار لا إرسال: النموذج حوله نموذج شراء. */
        e.preventDefault();
        choose(active);
      } else if (e.key === 'Escape') {
        e.preventDefault();
        close(true);
      } else if (e.key === 'Tab') {
        close(false);
      }
    });
    list.addEventListener('click', function (e) {
      var li = e.target.closest ? e.target.closest('.cc-pick__opt') : null;
      if (li) choose(parseInt(li.getAttribute('data-i'), 10));
    });
    list.addEventListener('mousemove', function (e) {
      var li = e.target.closest ? e.target.closest('.cc-pick__opt') : null;
      if (li) {
        var i = parseInt(li.getAttribute('data-i'), 10);
        if (i !== active) setActive(i);
      }
    });

    /* الدولة قد تتبدل من غير هذا الزر (`tq-phone.js` يستعيد المحفوظة). */
    sel.addEventListener('change', paintBtn);
    paintBtn();
    /* و`tq-phone.js` يكتب الدولة المحفوظة بلا `change` — وقد يجري بعد هذا
       الملف، فيعاد الرسم متى اكتملت الصفحة. */
    window.addEventListener('load', paintBtn);
  });
})();
