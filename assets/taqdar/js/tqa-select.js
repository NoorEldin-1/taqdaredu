/**
 * TQA-SELECT — منتق تقدر: صندوق يقول إنه صندوق.
 *
 * `<select>` الأصلي يرسمه **نظام التشغيل** لا الورقة: خطه خط النظام،
 * وقائمته المنسدلة نافذة خارج الصفحة لا يبلغها لون ولا نصف قطر ولا
 * اتجاه. وأثقل من الشكل أن حده — بعد `appearance: none` وسهم مرسوم —
 * صار مطابقا لحد `<input>` حرفا بحرف: فحقل يكتب فيه وحقل يفتح منه
 * يقرآن شيئا واحدا، ومن يمسح شبكة من ستة عشر حقلا بعينه لا يعرف أيها
 * ينقر وأيها يكتب. وهذا هو ما اشتكي منه.
 *
 * وثلاث قواعد تحكم هذا الملف:
 *
 * ١ — **المنتق الأصلي يبقى، وهو مصدر الحقيقة.** لا يحذف ولا يستبدل
 *     بحقول مخفية: قيمته هي ما يرسل، و`required` تفحص عليه، و
 *     `tqa_formlogic` تقرؤه وتعطله وتخفيه، و`$('#x').val(...)` في اثنتي
 *     عشرة شاشة موروثة تكتب فيه. فالمبني هنا **واجهة فوقه** تتبعه
 *     ويتبعها، لا بديل عنه. ولو كان بديلا لسقط كل ما سبق بلا خطأ يظهر.
 *
 * ٢ — **اللوح `position: fixed`.** المنتقيات تعيش داخل `.tqa-table__wrap`
 *     وداخل `.tqa-menu__pop` وكلاهما `overflow: auto` — والمطلق يقص عند
 *     حافتهما، فتفتح قائمة الصف الأخير نصفها تحت الجدول. وهي علة قائمة
 *     الصف نفسها (TQ-ROW-CLUTTER)، وعلاجها هو هو: الموضع يحسب من مستطيل
 *     الزر لا من الشجرة.
 *
 * ٣ — **البحث يسوي العربية قبل أن يقارن.** «الأول» و«الاول» كتابتان
 *     لكلمة واحدة، ومنتق أربعين مادة لا يبحث فيه إلا من يعرف بأي همزة
 *     كتبت. فالمقارنة على نص مسوى: الهمزات ألف، والتاء المربوطة هاء،
 *     والألف المقصورة ياء، والتطويل يسقط.
 *
 * ولا مكتبة: `select2` منزوعة من اللوحة أصلا (TQ-SELECT2-GONE)، وإعادتها
 * تعني ثمانين كيلوبايت وسمة ثالثة لا تشبه شيئا هنا.
 */
(function (window, document) {
    'use strict';

    var TQA = window.TQA = window.TQA || {};

    var SEARCH_FROM = 8;   /* حقل البحث يظهر من هذا العدد فصاعدا */
    var BULK_FROM   = 6;   /* «حدد الكل» و«امسح» في المتعدد */
    var CHIP_MAX    = 3;   /* حبات تعرض في الزر، وما بعدها يعد */
    var GAP = 6, EDGE = 8, MIN_W = 224, MAX_W = 420;

    var open = null;       /* الحالة المفتوحة الآن */
    var veil = null;
    var seq  = 0;

    function rtl() {
        return (document.documentElement.getAttribute('dir') || '').toLowerCase() === 'rtl' ||
               getComputedStyle(document.documentElement).direction === 'rtl';
    }

    function sheet() { return window.innerWidth < 640; }

    /**
     * تسوية النص العربي قبل المقارنة — انظر القاعدة الثالثة في الرأس.
     * ولا تمس القيمة المرسلة: هذه للمطابقة وحدها.
     */
    function norm(s) {
        return String(s == null ? '' : s)
            .toLowerCase()
            .replace(/[ً-ْـٰ]/g, '')
            .replace(/[أإآٱ]/g, 'ا')
            .replace(/ى/g, 'ي')
            .replace(/ة/g, 'ه')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function svg(path, size) {
        return '<svg viewBox="0 0 24 24" width="' + size + '" height="' + size + '" fill="none"' +
               ' stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"' +
               ' aria-hidden="true">' + path + '</svg>';
    }

    /* =====================================================================
       ١ · من يترقى ومن لا

       القاعدة: كل منتق في اللوحة إلا ما أعلن استثناءه. وعد المنتقيات
       شاشة شاشة لا يجدي — الشاشة الرابعة والخمسون التي تكتب غدا تحتاج
       أن تحصل على الصندوق نفسه بلا أن يتذكر كاتبها سطرا.
       ===================================================================== */
    function skip(sel) {
        if (!sel || sel.tagName !== 'SELECT') return true;
        if (sel.dataset.tqaSelOn === '1') return true;
        if (sel.hasAttribute('data-tqa-noselect')) return true;
        /* محررات ومنتقيات تبنيها مكتبات طرف ثالث ثم تكتب فوق وسمها:
           ترقيتها تعني صندوقين أحدهما لا يعرف الآخر. */
        if (sel.closest('[data-tqa-noselect], .note-editor, .note-toolbar, .dropzone, .iconpicker, .tqa-sel')) return true;
        return false;
    }

    /* =====================================================================
       ٢ · البناء
       ===================================================================== */

    function build(sel) {
        seq++;
        var id = 'tqasel' + seq;
        var st = { sel: sel, opts: [], groups: [], active: -1, filter: '' };

        sel.dataset.tqaSelOn = '1';

        var wrap = st.wrap = document.createElement('div');
        wrap.className = 'tqa-sel' + (sel.multiple ? ' tqa-sel--multi' : '');
        /* أصناف الحجم تنتقل: منتق داخل شريط أدوات أو قائمة صف ارتفاعه ٣٦
           لا ٤٤، وصندوق بارتفاع الحقل الكامل هناك يجعل الشريط سطرين. */
        if (/(^|\s)(tqa-select--sm|tqa-input--sm|form-control-sm|custom-select-sm)(\s|$)/.test(sel.className)) {
            wrap.classList.add('tqa-sel--sm');
        }
        sel.parentNode.insertBefore(wrap, sel);
        wrap.appendChild(sel);
        sel.classList.add('tqa-sel__native');
        sel.setAttribute('tabindex', '-1');

        /* ---- الزر ---- */
        var btn = st.btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'tqa-sel__btn';
        btn.setAttribute('aria-haspopup', 'listbox');
        btn.setAttribute('aria-expanded', 'false');
        btn.setAttribute('aria-controls', id);
        btn.innerHTML = '<span class="tqa-sel__val"></span>' +
                        '<span class="tqa-sel__caret" aria-hidden="true">' +
                        svg('<path d="m6 9.5 6 6 6-6"/>', 15) + '</span>';
        wrap.appendChild(btn);

        /* التسمية تشير إلى المنتق الأصلي وهو غير مرئي، فضغطها كان يذهب
           إلى لا شيء. وهي أول ما يضغطه من يتنقل بالنقر لا بالتاب. */
        var lbl = null;
        if (sel.id) {
            try { lbl = document.querySelector('label[for="' + (window.CSS && CSS.escape ? CSS.escape(sel.id) : sel.id) + '"]'); }
            catch (e) { lbl = null; }
        }
        if (lbl) {
            if (!lbl.id) lbl.id = id + 'l';
            btn.setAttribute('aria-labelledby', lbl.id);
            lbl.addEventListener('click', function (e) { e.preventDefault(); btn.focus(); });
        } else if (sel.getAttribute('aria-label')) {
            btn.setAttribute('aria-label', sel.getAttribute('aria-label'));
        }

        /* ---- اللوح ---- */
        var pop = st.pop = document.createElement('div');
        pop.className = 'tqa-sel__pop';
        pop.id = id;
        pop.hidden = true;
        pop.innerHTML =
            '<div class="tqa-sel__search" hidden>' +
                '<span class="tqa-sel__searchic" aria-hidden="true">' +
                svg('<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>', 15) + '</span>' +
                '<input type="text" class="tqa-sel__q" autocomplete="off" spellcheck="false">' +
            '</div>' +
            '<div class="tqa-sel__bulk" hidden>' +
                '<button type="button" class="tqa-sel__bulkbtn" data-all></button>' +
                '<button type="button" class="tqa-sel__bulkbtn" data-none></button>' +
                '<span class="tqa-sel__count" data-count></span>' +
            '</div>' +
            '<div class="tqa-sel__list" role="listbox"></div>' +
            '<p class="tqa-sel__none" hidden></p>';
        wrap.appendChild(pop);

        st.list  = pop.querySelector('.tqa-sel__list');
        st.qbox  = pop.querySelector('.tqa-sel__search');
        st.q     = pop.querySelector('.tqa-sel__q');
        st.bulk  = pop.querySelector('.tqa-sel__bulk');
        st.count = pop.querySelector('[data-count]');
        st.none  = pop.querySelector('.tqa-sel__none');

        st.q.placeholder = TQ.t('ابحث في القائمة…');
        st.q.setAttribute('aria-label', st.q.placeholder);
        st.none.textContent = TQ.t('لا خيار يطابق ما كتبت.');
        st.bulk.querySelector('[data-all]').textContent  = TQ.t('حدد الكل');
        st.bulk.querySelector('[data-none]').textContent = TQ.t('امسح التحديد');
        if (sel.multiple) st.list.setAttribute('aria-multiselectable', 'true');

        wire(st);

        /* ---- البنود تبنى عند أول فتح لا عند تحميل الصفحة ----
           منتق «سجل طالبا» يحمل ألفي مستخدم، وشاشة الاشتراكات تحمل ثلاثين
           منتقا. وبناء بنود كل واحد منها وقت التحميل يعني عشرات الآلاف من
           العناصر لصناديق أكثرها لا يفتح في الجلسة كلها — والصفحة تتجمد
           ثانية كاملة قبل أن تظهر.
           وما يحتاجه الزر قبل الفتح — النص المختار وحده — يقرأ من
           `select.options` مباشرة، لا من البنود. */
        st.built = false;
        st.qbox.hidden = sel.options.length < SEARCH_FROM;
        st.bulk.hidden = !sel.multiple || sel.options.length < BULK_FROM;
        paint(st);

        sel.__tqaSel = st;
        return st;
    }

    /* =====================================================================
       ٣ · الرسم — البنود ثم الزر

       `render` يعيد بناء البنود (عند تغير الخيارات)، و`paint` يحدث ما
       تغير من حال (المحدد والمخفي والمعطل) بلا هدم. والفصل مقصود: شاشة
       تكتب أربعين خيارا بجافاسكربت ثم تبدل واحدا كانت تعيد بناء
       الأربعين مع كل ضغطة.
       ===================================================================== */

    function optText(o) {
        return String(o.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function optNode(st, o) {
        var el = document.createElement('div');
        el.className = 'tqa-sel__opt';
        el.setAttribute('role', 'option');
        el.__opt = o;
        el.innerHTML = '<span class="tqa-sel__tick" aria-hidden="true">' +
                       svg('<path d="m5 12 4.5 4.5L19 7"/>', 13) + '</span>' +
                       '<span class="tqa-sel__optlabel"></span>';
        el.querySelector('.tqa-sel__optlabel').textContent = optText(o) || ' ';
        el.__key = norm(optText(o));
        st.opts.push(el);
        return el;
    }

    function render(st) {
        st.list.textContent = '';
        st.opts = [];
        st.groups = [];

        var kids = st.sel.children;
        for (var i = 0; i < kids.length; i++) {
            var k = kids[i];
            if (k.tagName === 'OPTGROUP') {
                var g = document.createElement('div');
                g.className = 'tqa-sel__group';
                var gl = document.createElement('span');
                gl.className = 'tqa-sel__grouplabel';
                gl.textContent = k.label || '';
                g.appendChild(gl);
                for (var j = 0; j < k.children.length; j++) {
                    if (k.children[j].tagName === 'OPTION') g.appendChild(optNode(st, k.children[j]));
                }
                st.groups.push(g);
                st.list.appendChild(g);
            } else if (k.tagName === 'OPTION') {
                st.list.appendChild(optNode(st, k));
            }
        }

        var n = st.opts.length;
        st.qbox.hidden = n < SEARCH_FROM;
        st.bulk.hidden = !st.sel.multiple || n < BULK_FROM;
        st.built = true;
        paint(st);
    }

    /** حبة في الزر — الاسم وزر ينزعه. */
    function chip(st, o) {
        var c = document.createElement('span');
        c.className = 'tqa-sel__chip';
        var s = document.createElement('span');
        s.textContent = optText(o);
        c.appendChild(s);

        var x = document.createElement('button');
        x.type = 'button';
        x.className = 'tqa-sel__chipx';
        x.setAttribute('aria-label', TQ.t('أزل ____', s.textContent));
        x.innerHTML = svg('<path d="M6 6l12 12M18 6 6 18"/>', 11);
        /* `mousedown` يوقف كذلك: النقر على الحبة يقع على الزر الحاوي،
           فيفتح اللوح في اللحظة نفسها التي ينزع فيها الخيار. */
        x.addEventListener('mousedown', function (e) { e.preventDefault(); e.stopPropagation(); });
        x.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            o.selected = false;
            fire(st);
            paint(st);
        });
        c.appendChild(x);
        return c;
    }

    function paint(st) {
        var sel = st.sel, multi = sel.multiple, i;
        var on = 0, shown = 0;

        for (i = 0; i < st.opts.length; i++) {
            var el = st.opts[i], o = el.__opt;
            var off = !!(o.disabled || (o.parentNode && o.parentNode.disabled));
            el.setAttribute('aria-selected', o.selected ? 'true' : 'false');
            el.classList.toggle('is-on', !!o.selected);
            el.classList.toggle('is-off', off);
            if (off) el.setAttribute('aria-disabled', 'true'); else el.removeAttribute('aria-disabled');
            /* المخفي بـ`option.hidden` — يكتبه مرشح `data-tqa-filter` في
               [admin.js] — يبقى محددا ويرسل، ولا يعرض. */
            el.hidden = !!o.hidden || (st.filter !== '' && el.__key.indexOf(st.filter) === -1);
            if (!el.hidden) shown++;
            if (o.selected) on++;
        }
        for (i = 0; i < st.groups.length; i++) {
            st.groups[i].hidden = !st.groups[i].querySelector('.tqa-sel__opt:not([hidden])');
        }
        st.none.hidden = !st.built || shown > 0;

        var val = st.btn.querySelector('.tqa-sel__val');
        val.textContent = '';
        st.btn.classList.remove('is-empty');

        if (multi) {
            var picked = [];
            for (i = 0; i < sel.options.length; i++) if (sel.options[i].selected) picked.push(sel.options[i]);
            if (!picked.length) {
                st.btn.classList.add('is-empty');
                val.textContent = sel.getAttribute('data-tqa-placeholder') || TQ.t('اختر من القائمة');
            } else {
                var box = document.createElement('span');
                box.className = 'tqa-sel__chips';
                for (i = 0; i < picked.length && i < CHIP_MAX; i++) box.appendChild(chip(st, picked[i]));
                if (picked.length > CHIP_MAX) {
                    var more = document.createElement('span');
                    more.className = 'tqa-sel__more';
                    more.textContent = '+' + (picked.length - CHIP_MAX);
                    box.appendChild(more);
                }
                val.appendChild(box);
            }
            if (st.count) st.count.textContent = on ? TQ.t('المحدد ____', on) : TQ.t('لم يحدد شيء');
        } else {
            var cur = sel.selectedIndex >= 0 ? sel.options[sel.selectedIndex] : null;
            var txt = cur ? optText(cur) : '';
            /* «— بلا تحديد —» ليست قيمة يقرؤها القارئ اختيارا: تعرض بحبر
               باهت كما يعرض نائب الحقل النصي، فتفرق العين بين حقل ملئ
               وحقل لم يملأ في شبكة من ستة عشر حقلا. */
            var empty = !cur || cur.value === '' || /^[\s—–-]/.test(txt);
            st.btn.classList.toggle('is-empty', empty);
            val.textContent = txt || (sel.getAttribute('data-tqa-placeholder') || TQ.t('اختر من القائمة'));
        }

        /* الحال منقولة من المنتق الأصلي: من عطله أو أخفاه عطل الصندوق
           وأخفاه معه — و`tqa_formlogic` تفعل الاثنين في كل نموذج موصوف. */
        st.btn.disabled = sel.disabled;
        st.wrap.classList.toggle('is-disabled', sel.disabled);
        st.wrap.hidden = sel.hidden;
        if ((sel.disabled || sel.hidden) && open === st) shut(false);
    }

    /** الحدثان معا: `input` لحارس التعديل غير المحفوظ، و`change` لكل ما سواه. */
    function fire(st) {
        st.sel.dispatchEvent(new Event('input',  { bubbles: true }));
        st.sel.dispatchEvent(new Event('change', { bubbles: true }));
    }

    /* =====================================================================
       ٤ · الاختيار
       ===================================================================== */

    function pick(st, el) {
        var o = el.__opt;
        if (!o || o.disabled) return;

        if (st.sel.multiple) {
            o.selected = !o.selected;
            fire(st);
            paint(st);
            setActive(st, st.opts.indexOf(el));
            place(st);
            return;
        }
        /* `option.selected` لا `select.value`: قائمتان قد تحملان القيمة
           نفسها بنصين (الصفوف والمراحل)، و`value` تختار أولاهما. */
        o.selected = true;
        fire(st);
        paint(st);
        shut(true);
    }

    function setAll(st, yes) {
        for (var i = 0; i < st.opts.length; i++) {
            var el = st.opts[i];
            /* على **المعروض** لا على الكل: من بحث بكلمة ثم ضغط «حدد الكل»
               يقصد ما أمامه، وتحديد الأربعين بعده مفاجأة تحفظ. وهي قاعدة
               `tqa-picks` نفسها. */
            if (el.hidden || el.__opt.disabled) continue;
            el.__opt.selected = yes;
        }
        fire(st);
        paint(st);
        place(st);
    }

    /* =====================================================================
       ٥ · الموضع — يحسب من مستطيل الزر، لا من الشجرة
       ===================================================================== */

    function place(st) {
        var p = st.pop, b = st.btn;
        if (!p || p.hidden) return;

        if (sheet()) {
            p.classList.add('is-sheet');
            p.style.top = p.style.left = p.style.inlineSize = p.style.maxBlockSize = '';
            return;
        }
        p.classList.remove('is-sheet');

        var r = b.getBoundingClientRect();
        /* العرض عرض الزر، محصورا بين حدين: منتق السعر في شاشة الأوقات
           عرضه ٨٨ بكسلا فلوح بعرضه يكسر كل اسم سطرين، ومنتق في خلية
           جدول عرضها تسعمئة يعطي لوحا بعرض الشاشة تقرأ فيه العين سطرا
           واحدا لا قائمة. */
        p.style.inlineSize = Math.min(Math.max(r.width, MIN_W), MAX_W) + 'px';

        var w = p.offsetWidth;
        var h = p.offsetHeight;
        var left = rtl() ? (r.right - w) : r.left;
        left = Math.max(EDGE, Math.min(left, window.innerWidth - w - EDGE));

        var below = window.innerHeight - r.bottom - GAP - EDGE;
        var above = r.top - GAP - EDGE;
        var top;
        if (h <= below || below >= above) {
            top = r.bottom + GAP;
            p.style.maxBlockSize = Math.max(160, below) + 'px';
        } else {
            p.style.maxBlockSize = Math.max(160, above) + 'px';
            top = Math.max(EDGE, r.top - Math.min(h, above) - GAP);
        }

        p.style.top  = Math.round(top) + 'px';
        p.style.left = Math.round(left) + 'px';
    }

    /* =====================================================================
       ٦ · الفتح والإغلاق
       ===================================================================== */

    function shut(back) {
        if (!open) return;
        var st = open;
        open = null;
        st.pop.hidden = true;
        st.pop.classList.remove('is-sheet');
        st.pop.style.maxBlockSize = '';
        st.wrap.classList.remove('is-open');
        st.btn.setAttribute('aria-expanded', 'false');
        if (veil) { veil.remove(); veil = null; }
        /* البحث لا يبقى مكتوبا: من فتح القائمة ثانية يريد كل الخيارات
           لا آخر ما بحث عنه، ولا شيء في الزر يقول إن فيها مرشحا. */
        if (st.q) st.q.value = '';
        st.filter = '';
        setActive(st, -1);
        paint(st);
        if (back) { try { st.btn.focus(); } catch (e) {} }
    }

    function show(st) {
        if (open === st) { shut(true); return; }
        shut(false);
        if (st.sel.disabled) return;
        if (!st.built) render(st);

        veil = document.createElement('div');
        veil.className = 'tqa-sel-veil';
        veil.addEventListener('mousedown', function (e) { e.preventDefault(); shut(false); });
        document.body.appendChild(veil);

        st.pop.hidden = false;
        st.wrap.classList.add('is-open');
        st.btn.setAttribute('aria-expanded', 'true');
        open = st;
        place(st);

        /* أول محدد يستقبل الإشارة، فالقائمة الطويلة تفتح على ما هو مختار
           لا على أولها: منتق فيه أربعون صفا واختياره الخامس والثلاثون
           كان يفتح على الأول فيظن صاحبه أن اختياره ضاع. */
        var i = firstOn(st);
        setActive(st, i >= 0 ? i : firstUsable(st));
        scrollTo(st);

        if (!st.qbox.hidden) setTimeout(function () { try { st.q.focus(); } catch (e) {} }, 0);
        else setTimeout(function () { try { st.list.focus(); } catch (e) {} }, 0);
    }

    function firstOn(st) {
        for (var i = 0; i < st.opts.length; i++) if (st.opts[i].__opt.selected && !st.opts[i].hidden) return i;
        return -1;
    }
    function firstUsable(st) {
        for (var i = 0; i < st.opts.length; i++) if (!st.opts[i].hidden && !st.opts[i].__opt.disabled) return i;
        return -1;
    }

    function setActive(st, i) {
        for (var k = 0; k < st.opts.length; k++) st.opts[k].classList.remove('is-active');
        st.active = i;
        if (i < 0 || !st.opts[i]) {
            st.list.removeAttribute('aria-activedescendant');
            return;
        }
        var el = st.opts[i];
        el.classList.add('is-active');
        if (!el.id) el.id = st.pop.id + 'o' + i;
        st.list.setAttribute('aria-activedescendant', el.id);
    }

    function scrollTo(st) {
        var el = st.opts[st.active];
        if (!el || el.hidden) return;
        var top = el.offsetTop, h = el.offsetHeight;
        if (top < st.list.scrollTop) st.list.scrollTop = top - 4;
        else if (top + h > st.list.scrollTop + st.list.clientHeight) {
            st.list.scrollTop = top + h - st.list.clientHeight + 4;
        }
    }

    function move(st, step) {
        var n = st.opts.length;
        if (!n) return;
        var i = st.active;
        for (var k = 0; k < n; k++) {
            i += step;
            if (i < 0) i = n - 1;
            if (i >= n) i = 0;
            if (!st.opts[i].hidden && !st.opts[i].__opt.disabled) { setActive(st, i); scrollTo(st); return; }
        }
    }

    function edge(st, last) {
        var n = st.opts.length;
        for (var k = 0; k < n; k++) {
            var i = last ? n - 1 - k : k;
            if (!st.opts[i].hidden && !st.opts[i].__opt.disabled) { setActive(st, i); scrollTo(st); return; }
        }
    }

    /* =====================================================================
       ٧ · الربط
       ===================================================================== */

    function wire(st) {
        st.btn.addEventListener('click', function (e) {
            e.preventDefault();
            show(st);
        });

        st.btn.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                show(st);
            }
        });

        /* النقر بالبند لا بالضغطة: `mousedown` يسبق فقد التركيز من حقل
           البحث، ولو انتظرنا `click` لأغلقت الطبقة اللوح قبله. */
        st.list.addEventListener('mousedown', function (e) {
            var el = e.target.closest ? e.target.closest('.tqa-sel__opt') : null;
            if (!el) return;
            e.preventDefault();
            pick(st, el);
        });
        st.list.addEventListener('mousemove', function (e) {
            var el = e.target.closest ? e.target.closest('.tqa-sel__opt') : null;
            if (!el || el.hidden || el.__opt.disabled) return;
            setActive(st, st.opts.indexOf(el));
        });

        st.bulk.querySelector('[data-all]').addEventListener('click', function (e) { e.preventDefault(); setAll(st, true); });
        st.bulk.querySelector('[data-none]').addEventListener('click', function (e) { e.preventDefault(); setAll(st, false); });

        st.q.addEventListener('input', function (e) {
            /* الحدث لا يصعد إلى النموذج: حارس التعديل غير المحفوظ
               (TQ-FORM-DIRTY) يمسك كل `input` فيه، وحقل البحث هذا **لا
               يغير قيمة** — فمن فتح منتقا وبحث فيه ثم أغلقه بلا اختيار
               كان يقال له عند المغادرة إن في الشاشة تعديلا لم يحفظ. */
            e.stopPropagation();
            st.filter = norm(st.q.value);
            paint(st);
            setActive(st, firstUsable(st));
            place(st);
        });

        /* مستمع واحد على اللوح لا مستمعان: الحدث يصعد من القائمة ومن حقل
           البحث إليه، ومستمع ثان على القائمة يجعل السهم الواحد ينقل
           بندين. و`tabIndex` على القائمة ليستقبلها التركيز متى لم يكن
           في اللوح حقل بحث. */
        st.list.tabIndex = -1;
        st.pop.addEventListener('keydown', function (e) { keys(st, e); });

        /* ---- ما يكتب في المنتق الأصلي من خارج ----
           `$('#x').val(v).trigger('change')` في الشاشات الموروثة، و
           `setEnabled()` في منطق النموذج، وإعادة بناء الخيارات بنداء
           AJAX. وبلا هذا يبقى الصندوق يعرض ما كان ويرسل النموذج ما صار
           — وهو أسوأ من ألا يعمل: يعرض قيمة ويحفظ غيرها. */
        st.sel.addEventListener('change', function () { paint(st); });

        var mo = new MutationObserver(function (recs) {
            var deep = false;
            for (var i = 0; i < recs.length; i++) {
                if (recs[i].type === 'childList' || recs[i].target !== st.sel) { deep = true; break; }
            }
            clearTimeout(st.tmr);
            st.tmr = setTimeout(function () {
                /* ما لم يفتح بعد لا بنود له تعاد: يبنى عند فتحه من الخيارات
                   كما صارت، فالتحديث مجاني. وما فتح يعاد بناؤه هنا. */
                if (deep && st.built) render(st);
                else paint(st);
            }, 30);
        });
        mo.observe(st.sel, {
            childList: true, subtree: true, attributes: true,
            attributeFilter: ['disabled', 'hidden', 'multiple', 'required', 'selected', 'value']
        });

        var form = st.sel.form;
        if (form) form.addEventListener('reset', function () { setTimeout(function () { paint(st); }, 0); });
    }

    function keys(st, e) {
        if (e.key === 'Escape')      { e.preventDefault(); e.stopPropagation(); shut(true); return; }
        if (e.key === 'Tab')         { shut(false); return; }
        if (e.key === 'ArrowDown')   { e.preventDefault(); move(st, 1); return; }
        if (e.key === 'ArrowUp')     { e.preventDefault(); move(st, -1); return; }
        if (e.key === 'Home')        { e.preventDefault(); edge(st, false); return; }
        if (e.key === 'End')         { e.preventDefault(); edge(st, true); return; }
        if (e.key === 'Enter') {
            e.preventDefault();
            /* Enter في لوح مفتوح يختار ولا يرسل النموذج: من بحث ثم أكد
               بحثه كان يحفظ الشاشة نصف محررة. */
            if (st.opts[st.active]) pick(st, st.opts[st.active]);
            return;
        }
        if (e.key === ' ' && e.target !== st.q) {
            e.preventDefault();
            if (st.opts[st.active]) pick(st, st.opts[st.active]);
        }
    }

    /* =====================================================================
       ٨ · المسح: عند التحميل، وعند كل ما يضاف بعده

       النوافذ الموروثة (`showAjaxModal`) تجلب نماذجها بـAJAX وفيها
       منتقيات، والجداول تعاد رسما. فمسحة واحدة عند التحميل تترك نصف
       اللوحة بصناديق النظام ونصفها بصناديقنا — وهو أسوأ من واحد منهما.
       ===================================================================== */

    function scan(root) {
        var box = root || document;
        if (box.querySelectorAll) {
            var list = box.querySelectorAll('select');
            for (var i = 0; i < list.length; i++) if (!skip(list[i])) build(list[i]);
        }
        if (box.tagName === 'SELECT' && !skip(box)) build(box);
    }

    var pending = null;
    function later() {
        clearTimeout(pending);
        pending = setTimeout(function () { scan(document); }, 60);
    }

    function boot() {
        scan(document);
        new MutationObserver(function (recs) {
            for (var i = 0; i < recs.length; i++) {
                var add = recs[i].addedNodes;
                for (var j = 0; j < add.length; j++) {
                    if (add[j].nodeType !== 1) continue;
                    if (add[j].tagName === 'SELECT' || (add[j].querySelector && add[j].querySelector('select'))) {
                        later();
                        return;
                    }
                }
            }
        }).observe(document.documentElement, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();

    window.addEventListener('resize', function () { if (open) place(open); });
    /* التقاط: الجدول نفسه حاوية تمرر، وحدث تمررها لا يصعد إلى النافذة. */
    window.addEventListener('scroll', function () {
        if (!open) return;
        var r = open.btn.getBoundingClientRect();
        if (r.bottom < 0 || r.top > window.innerHeight) { shut(false); return; }
        place(open);
    }, true);

    TQA.select = {
        scan: scan,
        refresh: function (sel) {
            var st = sel && sel.__tqaSel;
            if (!st) return;
            if (st.built) render(st); else paint(st);
        }
    };

})(window, document);
