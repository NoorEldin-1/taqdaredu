<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<script>
/**
 * سحب لإعادة الترتيب — مشترك بين الأقسام والدروس والأقسام المخصصة.
 *
 * ثلاث نسخ من هذه الشيفرة كانت في ثلاثة قوالب، وفي كل نسخة مصنع
 * Dragula مضغوطا (وهو محمل في الذيل أصلا)، ونداء `$.ajax` بلا توكن،
 * و`location.reload()` بعد ثانية بلا فحص أن الخادم قبل.
 *
 * وهنا نسخة واحدة، وسحب أصلي بـHTML5 `draggable` لا مكتبة: الحاجة
 * ترتيب قائمة عمودية قصيرة، وDragula تكلف ملفا كاملا لأجلها.
 */
(function () {
    'use strict';

    var list = document.querySelector('[data-tqa-sortable]');
    var save = document.querySelector('[data-tqa-sort-save]');
    if (!list || !save) return;

    var items = function () {
        return Array.prototype.slice.call(list.querySelectorAll('[data-id]'));
    };

    var dragged = null;

    /* أرقام الترتيب تعاد كتابتها بعد كل نقلة، فما يقرؤه المستخدم
       يطابق ما سيحفظ — لا رقما ثابتا يكذب على موضع البطاقة. */
    var renumber = function () {
        items().forEach(function (el, i) {
            var n = el.querySelector('[data-tqa-pos]');
            if (n) n.textContent = i + 1;
        });
        save.disabled = false;
    };

    items().forEach(function (el) {
        el.setAttribute('draggable', 'true');

        el.addEventListener('dragstart', function () {
            dragged = el;
            el.style.opacity = '.45';
        });
        el.addEventListener('dragend', function () {
            el.style.opacity = '';
            dragged = null;
        });
        el.addEventListener('dragover', function (e) {
            e.preventDefault();
            if (!dragged || dragged === el) return;

            var box = el.getBoundingClientRect();
            var after = (e.clientY - box.top) > (box.height / 2);
            list.insertBefore(dragged, after ? el.nextSibling : el);
        });
        el.addEventListener('drop', function (e) { e.preventDefault(); renumber(); });

        /* بلوحة المفاتيح أيضا: السحب وحده يقصي من لا يستعمل فأرة. */
        el.setAttribute('tabindex', '0');
        el.addEventListener('keydown', function (e) {
            var up   = (e.key === 'ArrowUp');
            var down = (e.key === 'ArrowDown');
            if (!up && !down) return;
            e.preventDefault();

            var sib = up ? el.previousElementSibling : el.nextElementSibling;
            if (!sib) return;
            if (up) list.insertBefore(el, sib);
            else    list.insertBefore(sib, el);
            el.focus();
            renumber();
        });
    });

    save.addEventListener('click', function () {
        var ids = items().map(function (el) { return el.getAttribute('data-id'); });

        var body = new URLSearchParams();
        body.set('itemJSON', JSON.stringify(ids));
        ids.forEach(function (id) { body.append('order[]', id); });
        if (window.TQ_CSRF && TQ_CSRF.name) body.set(TQ_CSRF.name, TQ_CSRF.hash);

        save.disabled = true;
        save.textContent = 'يحفظ…';

        fetch(save.getAttribute('data-url'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (r) {
            /* الرد يفحص فعلا: صفحة خطأ كانت تعد نجاحا فيعاد التحميل
               ويرى المستخدم ترتيبه القديم بلا تفسير. */
            if (!r.ok) throw new Error(r.status);
            if (window.TQA) TQA.ok('حفظ الترتيب.');
            window.location.reload();
        }).catch(function () {
            save.disabled = false;
            save.textContent = 'احفظ الترتيب';
            if (window.TQA) TQA.error('تعذر حفظ الترتيب. أعد المحاولة.');
        });
    });
})();
</script>
