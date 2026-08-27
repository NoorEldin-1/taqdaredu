/**
 * TQ-PHONE-INTL · حقل الجوال يتبع دولته.
 *
 * ملف مستقل لأن الحقل يعيش في جهتين: نموذج التسجيل في الموقع العام
 * (`site.js`) وشاشات الإعدادات في البوابات (`taqdar.js`). ونسخة في كل
 * منهما تفترق عند أول تعديل، فتصلح شاشة وتبقى أختها.
 *
 * ولا يفحص شيئا: الفحص في `site.js` عند الإرسال وفي الخادم قطعا. هذا
 * ما يجعل الحقل يقول لصاحبه بأي صورة يكتب.
 */
/* المثال في `placeholder` هو ما يعلم صاحب الحقل بأي صورة يكتب: من
   بدل إلى «مصر» ورأى `512345678` سعوديا كتب رقما سعوديا الشكل ثم
   رفض. والحقل يمسح رسالته عند التبديل — الخطأ كان عن دولة أخرى.
   ويحفظ آخر دولة اختيرت: من عاد إلى الصفحة لا يعيد الانتقاء. */
(function () {
  var boxes = document.querySelectorAll('[data-tq-phone]');
  if (!boxes.length) return;
  var KEY = 'tq-phone-cc';

  var saved = null;
  try { saved = localStorage.getItem(KEY); } catch (e) {}

  Array.prototype.forEach.call(boxes, function (box) {
    var cc = box.querySelector('[data-tq-phone-cc]');
    var num = box.querySelector('[data-tq-phone-num]');
    if (!cc || !num) return;

    /* المحفوظ لا يعلو على قيمة محفوظة في القاعدة: من فتح شاشة إعداداته
       يجد دولة رقمه هو، لا آخر دولة انتقاها في نموذج آخر. */
    if (saved && !num.value) {
      var has = Array.prototype.some.call(cc.options, function (o) { return o.value === saved; });
      if (has) cc.value = saved;
    }

    function sync() {
      var o = cc.options[cc.selectedIndex];
      if (o) num.setAttribute('placeholder', o.getAttribute('data-ex') || '');
      var slot = box.parentNode && box.parentNode.querySelector('.field-err');
      if (slot) { slot.textContent = ''; slot.hidden = true; }
      box.classList.remove('form-field--invalid');
      num.removeAttribute('aria-invalid');
    }
    sync();

    cc.addEventListener('change', function () {
      sync();
      try { localStorage.setItem(KEY, cc.value); } catch (e) {}
      num.focus();
    });

    /* الأرقام وحدها في الحقل: من لصق `+20 100 123 4567` يقصد رقمه،
       والرمز فيه يكرر ما انتقاه — والخادم يقصه، ولكن ما يراه صاحبه
       هنا يجب أن يكون ما سيخزن. */
    num.addEventListener('blur', function () {
      var v = num.value.replace(/[٠-٩]/g, function (d) {
        return '٠١٢٣٤٥٦٧٨٩'.indexOf(d);
      });
      var o = cc.options[cc.selectedIndex];
      var dial = o ? (o.getAttribute('data-dial') || '') : '';
      var min = o ? parseInt(o.getAttribute('data-min'), 10) : 0;
      var max = o ? parseInt(o.getAttribute('data-max'), 10) : 15;
      var d = v.replace(/[^0-9]/g, '').replace(/^0+/, '');
      if (!d) { return; }
      if (dial && d.indexOf(dial) === 0) {
        var cut = d.slice(dial.length).replace(/^0+/, '');
        if (cut.length >= min && cut.length <= max) d = cut;
      }
      num.value = d;
    });
  });
})();
