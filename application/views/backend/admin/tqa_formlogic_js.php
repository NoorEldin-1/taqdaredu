<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<script>
/**
 * منطق النموذج العام: الإظهار المشروط، والمنتقي المتبدل، ومربعات الاختيار.
 *
 * والمبدأ الحاكم هنا: **الخادم رسم الحالة الصحيحة أصلا.** كل ما تفعله
 * هذه الأسطر هو مواكبة التغيير بعد الرسم — فمن لم يصله الملف يرى نموذجا
 * صحيحا للقيمة المحفوظة، ويحفظ ما يعنيه لأن `save()` تصفر ما لا يعنيه
 * النطاق على كل حال. ولا حقل هنا يوجد بالسكربت وحده.
 */
(function () {
    'use strict';

    var form = document.querySelector('[data-tqa-form]');
    if (!form || form.dataset.tqaLogicOn === '1') return;
    form.dataset.tqaLogicOn = '1';

    /* قيمة حقل مراقب — بالاسم كما ورد في `show_when`. */
    function valueOf(name) {
        var el = form.querySelector('[data-tqa-field="' + name + '"]');
        if (!el) el = form.querySelector('[name="' + name + '"]');
        return el ? String(el.value || '') : '';
    }

    /* الحقول المخفية تعطل مدخلاتها: حقل مخفي يرسل قيمته كأنه ظاهر،
       فيكتب في العمود ما لم يره المسؤول. */
    function setEnabled(box, on) {
        Array.prototype.forEach.call(box.querySelectorAll('input, select, textarea'), function (el) {
            if (el.type === 'hidden') return;
            el.disabled = !on;
        });
    }

    function applyConditions() {
        Array.prototype.forEach.call(form.querySelectorAll('[data-tqa-when]'), function (box) {
            var on   = box.getAttribute('data-tqa-when');
            var vals = String(box.getAttribute('data-tqa-when-val') || '').split(',');
            var show = vals.indexOf(valueOf(on)) !== -1;
            box.hidden = !show;
            setEnabled(box, show);
        });

        /* المنتقي المتبدل: واحد يعمل والبقية معطلة — والمعطل لا يرسل،
           فلا يصل العمود رقمان ولا رقم من التفسير الخطأ. */
        Array.prototype.forEach.call(form.querySelectorAll('[data-tqa-case]'), function (sel) {
            var parts = String(sel.getAttribute('data-tqa-case')).split(':');
            var act   = (valueOf(parts[0]) === parts[1]);
            sel.hidden   = !act;
            sel.disabled = !act;
        });
    }

    form.addEventListener('change', function (e) {
        if (e.target && (e.target.tagName === 'SELECT' || e.target.type === 'radio')) applyConditions();
    });
    applyConditions();

    /* ---- مربعات الاختيار المتعددة ----

       TQ-PICKS-WALL — شبكة الحبات تعمل على اثني عشر صفا؛ وعلى أربعين
       مادة تصير جدارا: لا ترتيب يمسك العين، ولا سبيل إلى الوصول إلى
       «الفيزياء» إلا بالعين سطرا سطرا. فأضيف حقل يرشح.

       والترشيح في المتصفح — خلافا لترشيح الكتالوج الذي في الخادم —
       لأنه **لا يغير قيمة**: ما خفي يبقى محددا ويرسل مع النموذج. وهو
       إخفاء عرض لا استبعاد بيانات. */
    Array.prototype.forEach.call(form.querySelectorAll('[data-tqa-picks]'), function (box) {
        var boxes = box.querySelectorAll('.tqa-pick input[type="checkbox"]');
        var picks = Array.prototype.slice.call(box.querySelectorAll('.tqa-pick'));
        var grid  = box.querySelector('.tqa-picks__grid');
        var acts  = box.querySelector('.tqa-picks__acts');
        var count = box.querySelector('[data-tqa-picks-count]');
        var all   = box.querySelector('[data-tqa-picks-all]');
        var none  = box.querySelector('[data-tqa-picks-none]');

        function tally() {
            var n = 0;
            Array.prototype.forEach.call(boxes, function (b) { if (b.checked) n++; });
            if (count) count.textContent = n
                ? <?php echo json_encode(t('المحدد ')); ?> + n
                : <?php echo json_encode(t('لم يحدد شيء')); ?>;
            box.dispatchEvent(new CustomEvent('tqa:picks', { bubbles: true }));
        }

        /* «حدد الكل» و«امسح» على **المعروض** لا على الكل: من رشح بكلمة
           ثم ضغط «حدد الكل» يقصد ما أمامه، وتحديد الأربعين كلها بعده
           مفاجأة تحفظ. وبلا ترشيح المعروض هو الكل، فلا شيء يتغير. */
        function setAll(on) {
            picks.forEach(function (p) {
                if (p.hidden) return;
                var b = p.querySelector('input[type="checkbox"]');
                if (b) b.checked = on;
            });
            tally();
        }

        if (all)  all.addEventListener('click',  function () { setAll(true); });
        if (none) none.addEventListener('click', function () { setAll(false); });
        box.addEventListener('change', tally);
        tally();

        /* حقل الترشيح يظهر حيث يلزم وحده: عشرة خيارات لا تبحث، وحقل
           فوقها يوحي بقائمة أطول مما ترى. */
        if (!acts || !grid || picks.length < 12) return;

        var wrap = document.createElement('span');
        wrap.className = 'tqa-search tqa-picks__search';
        wrap.innerHTML =
            '<span class="tqa-search__ic" aria-hidden="true">' +
            '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"' +
            ' stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="6.5"/>' +
            '<path d="m16 16 4.5 4.5"/></svg></span>';

        var q = document.createElement('input');
        q.type = 'search';
        q.className = 'tqa-input tqa-input--sm';
        /* بلا `name`: حقل يرسل هنا يصل إلى `save()` معاملا لا عمود له. */
        q.placeholder = <?php echo json_encode(t('رشح القائمة…')); ?>;
        q.setAttribute('aria-label', q.placeholder);
        wrap.appendChild(q);
        acts.insertBefore(wrap, acts.firstChild);

        var empty = document.createElement('p');
        empty.className = 'tqa-picks__none';
        empty.hidden = true;
        empty.textContent = <?php echo json_encode(t('لا اسم يطابق ما كتبت.')); ?>;
        grid.appendChild(empty);

        q.addEventListener('input', function () {
            var s = q.value.trim().toLowerCase();
            var seen = 0;
            picks.forEach(function (p) {
                var hit = !s || (p.textContent || '').toLowerCase().indexOf(s) !== -1;
                p.hidden = !hit;
                if (hit) seen++;
            });
            empty.hidden = seen > 0;
        });

        /* Enter في حقل الترشيح لا يرسل النموذج: من رشح ثم ضغط Enter
           ليؤكد ترشيحه كان يحفظ الباقة نصف محررة. */
        q.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') e.preventDefault();
        });
    });

    /* ---- النسبة ومتممها ----
       المخزن رقم واحد والمتمم مرآة، فلا تفترق النسبتان أبدا. ومن كتب
       ١٥ يرى ٨٥ وهو يكتب — لا بعد أن يحفظ ويفتح الصفحة العامة. */
    Array.prototype.forEach.call(form.querySelectorAll('[data-tqa-percent]'), function (el) {
        var id = el.getAttribute('data-tqa-percent-mirror');
        if (!id) return;
        var out  = document.getElementById(id);
        if (!out) return;
        var label = String(out.textContent || '').split('—')[0].trim();

        function paint() {
            var s = String(el.value || '').trim();
            if (s === '' || isNaN(parseFloat(s))) {
                /* الفارغ يعني «الافتراض العام» لا صفرا — فلا يعرض ١٠٠٪
                   للمنصة على باقة لم تضبط نسبتها بعد. */
                out.textContent = label + ' — بالافتراض العام';
                return;
            }
            var v = Math.max(0, Math.min(100, parseFloat(s)));
            out.textContent = label + ' — ' + String(Math.round((100 - v) * 100) / 100) + '%';
        }

        el.addEventListener('input', paint);
        el.addEventListener('change', paint);
        paint();
    });

    /* ---- معاينة الصورة المختارة ---- */
    Array.prototype.forEach.call(form.querySelectorAll('[data-tqa-imgpick]'), function (sel) {
        var prev = form.querySelector('[data-tqa-imgpreview="' + sel.id + '"]');
        if (!prev) return;
        var img  = prev.querySelector('img');
        var base = <?php echo json_encode(base_url('assets/taqdar/site/img/')); ?>;

        sel.addEventListener('change', function () {
            var v = String(sel.value || '');
            prev.hidden = (v === '');
            if (v !== '') img.src = base + v + '.webp';
        });
    });
})();
</script>
