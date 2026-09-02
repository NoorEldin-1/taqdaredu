/**
 * TQA-FILE — حقل الملف: ما اخترت، وما هو محفوظ، وما يفعل بكل منهما.
 *
 * `<input type="file">` الأصلي زر نظام: نصه «Choose File» و«No file
 * chosen» **بالإنجليزية دائما** مهما كانت لغة الصفحة، وخطه خط النظام،
 * ولا يقبل لونا ولا نصف قطر. وقد لبس في اللوحة لباسا (`.tqa-file`) في
 * خمس عشرة شاشة — وبقي في خمس عشرة أخرى عاريا كما جاء.
 *
 * وأثقل من الشكل ثلاثة أشياء لم يكن يقولها أحد:
 *
 * ١ — **ما هو محفوظ الآن.** شاشة الباقة تعرض صورة الغلاف، وشاشة
 *     المدونة لا تعرض شيئا: يفتح المسؤول التعديل فلا يعرف أثمة صورة
 *     أصلا، فيرفع بديلا عن صورة صحيحة لأنه ظن ألا صورة.
 *
 * ٢ — **كيف يحذف.** «احذف الصورة الحالية» كان مربع اختيار في نصف
 *     الشاشات ولا شيء في نصفها — ومن أراد إزالة صورة بلا بديل لم يجد
 *     إليها سبيلا إلا أن يرفع صورة بيضاء.
 *
 * ٣ — **ما اختير قبل الحفظ.** اسم الملف وحجمه ومعاينته. ورفع صورة ثم
 *     حفظ ثم اكتشاف أنها الخطأ رحلة كاملة عن شيء تراه العين في ثانية.
 *
 * والقواعد الثلاث التي يقوم عليها هذا الملف:
 *
 * · **الحقل الأصلي يبقى ويحمل الملف.** لا `FormData` ولا رفع بجافاسكربت:
 *   النموذج يرسل كما كان يرسل، ومن تعثر عنده هذا الملف يرى حقلا عاديا
 *   يعمل. وهو شرط `required` كذلك — حقل `display:none` يرد المتصفح عليه
 *   «not focusable» فلا يحفظ النموذج ولا يظهر سبب.
 *
 * · **يعم اللوحة بلا أن تكتب شاشة سطرا.** المسح على كل
 *   `input[type=file]`، والوسم الموروث (`.tqa-file`) يستوعب بما فيه —
 *   صورته الحالية ونص ملصقه — فلا تعدل ثلاثون شاشة ولا تفترق واحدة.
 *
 * · **الحذف قرار يعلن ويتراجع عنه.** المربع الذي يمحو يبقى في الوسم
 *   (هو ما يقرؤه الخادم)، وهذا يعطيه زرا يقول ما يفعل وأثرا يرى —
 *   والصف يعرض مشطوبا حتى يحفظ، فمن ضغطه سهوا يتراجع قبل أن يفوت.
 */
(function (window, document) {
    'use strict';

    var TQA = window.TQA = window.TQA || {};

    var IMG = /\.(png|jpe?g|gif|webp|svg|avif|bmp)$/i;

    function svg(path, size) {
        return '<svg viewBox="0 0 24 24" width="' + size + '" height="' + size + '" fill="none"' +
               ' stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"' +
               ' aria-hidden="true">' + path + '</svg>';
    }

    var IC = {
        up:    '<path d="M12 16V4"/><path d="m7.5 8.5 4.5-4.5 4.5 4.5"/><path d="M4 15v3.5A1.5 1.5 0 0 0 5.5 20h13a1.5 1.5 0 0 0 1.5-1.5V15"/>',
        img:   '<rect x="3" y="4.5" width="18" height="15" rx="2.5"/><circle cx="8.5" cy="10" r="1.6"/><path d="m4 17 4.7-4.4a1.6 1.6 0 0 1 2.2 0L16 17"/><path d="m13.5 14.5 1.8-1.7a1.6 1.6 0 0 1 2.2 0L20 15"/>',
        file:  '<path d="M14 3H7.5A1.5 1.5 0 0 0 6 4.5v15A1.5 1.5 0 0 0 7.5 21h9a1.5 1.5 0 0 0 1.5-1.5V7z"/><path d="M14 3v4h4"/>',
        x:     '<path d="M6 6l12 12M18 6 6 18"/>',
        trash: '<path d="M4 7h16"/><path d="M9.5 7V5.4A1.4 1.4 0 0 1 10.9 4h2.2a1.4 1.4 0 0 1 1.4 1.4V7"/><path d="M6.6 7 7.5 19a1.6 1.6 0 0 0 1.6 1.5h5.8A1.6 1.6 0 0 0 16.5 19L17.4 7"/>',
        undo:  '<path d="M4 9h10a5 5 0 0 1 0 10h-3"/><path d="m8 5-4 4 4 4"/>'
    };

    /** حجم يقرؤه إنسان — والرقم لاتيني معزول فلا ينقلب في سطر عربي. */
    function size(n) {
        if (!n && n !== 0) return '';
        if (n < 1024) return TQ.t('____ بايت', n);
        if (n < 1024 * 1024) return TQ.t('____ ك.ب', (n / 1024).toFixed(0));
        return TQ.t('____ م.ب', (n / (1024 * 1024)).toFixed(n < 10 * 1024 * 1024 ? 1 : 0));
    }

    function isImage(f) {
        return f && ((f.type && f.type.indexOf('image/') === 0) || IMG.test(f.name || ''));
    }

    /* =====================================================================
       ١ · من يترقى — كل حقل ملف إلا ما أعلن استثناءه
       ===================================================================== */
    function skip(inp) {
        if (!inp || inp.type !== 'file') return true;
        if (inp.dataset.tqaDropOn === '1') return true;
        if (inp.hasAttribute('data-tqa-nodrop')) return true;
        if (inp.closest('[data-tqa-nodrop], .dropzone, .note-editor')) return true;
        return false;
    }

    /* =====================================================================
       ٢ · البناء
       ===================================================================== */

    function build(inp) {
        inp.dataset.tqaDropOn = '1';

        /* الوسم الموروث يستوعب بما فيه: صورته الحالية ونص ملصقه وسطر
           تلميحه. وبلا هذا يخرج الحقل مرتين — لباسه القديم وصندوقنا. */
        var legacy = inp.closest('.tqa-file');
        var pane   = inp.closest('.tqa-filefield');
        var host   = legacy || null;

        var cur  = inp.getAttribute('data-tqa-current') || '';
        var hint = inp.getAttribute('data-tqa-hint') || '';
        var cta  = '';

        if (legacy) {
            var pv = legacy.querySelector('img');
            if (pv && !cur) cur = pv.getAttribute('src') || '';
            var nm = legacy.querySelector('.tqa-file__name, [data-tqa-file-name]');
            if (nm && !hint) hint = (nm.textContent || '').trim();
            var lb = legacy.querySelector('.tqa-file__btn');
            if (lb) cta = (lb.textContent || '').trim();
        }
        if (pane) {
            var img = pane.querySelector('[data-tqa-file-cur]');
            if (img && !cur) cur = img.getAttribute('src') || '';
        }

        /* مربع المحو يبقى في الوسم — هو ما يقرؤه الخادم — ويخفى، ويقوده
           زر يقول ما يفعل. والبحث بالاسم لا بالموضع: الوسم يكتبه قالبان. */
        var clear = null;
        var scope = pane || legacy || (inp.form || document);
        if (inp.name) {
            clear = scope.querySelector('input[type=checkbox][name="' + inp.name.replace(/"/g, '\\"') + '__clear"]');
        }
        if (!clear && scope.querySelector) clear = scope.querySelector('[data-tqa-file-clear]');

        var box = document.createElement('div');
        box.className = 'tqa-drop';
        if (inp.multiple) box.classList.add('tqa-drop--multi');

        var anchor = host || inp;
        anchor.parentNode.insertBefore(box, anchor);
        box.appendChild(inp);
        inp.classList.add('tqa-drop__input');
        /* الحقل يبقى مرسوما لأجل `required` (انظر الرأس)، ويخرج من ترتيب
           التنقل: من يتنقل بالتاب كان يقف على صندوق شفاف بلا اسم ولا حد،
           ثم يقف على الزر الذي يفتح الشيء نفسه. */
        inp.setAttribute('tabindex', '-1');

        var accept = (inp.getAttribute('accept') || '').trim();
        var wantsImg = accept === '' ? IMG.test(cur) : /image/i.test(accept);

        box.insertAdjacentHTML('beforeend',
            '<button type="button" class="tqa-drop__zone">' +
                '<span class="tqa-drop__ic" aria-hidden="true">' + svg(wantsImg ? IC.img : IC.up, 20) + '</span>' +
                '<span class="tqa-drop__say"><b></b><span class="tqa-drop__hint"></span></span>' +
                '<span class="tqa-drop__cta"></span>' +
            '</button>' +
            '<ul class="tqa-drop__items"></ul>' +
            '<p class="tqa-drop__err" role="alert" hidden></p>');

        var zone  = box.querySelector('.tqa-drop__zone');
        var items = box.querySelector('.tqa-drop__items');
        var err   = box.querySelector('.tqa-drop__err');

        zone.querySelector('b').textContent = inp.multiple
            ? TQ.t('اسحب الملفات إلى هنا أو اخترها')
            : TQ.t('اسحب الملف إلى هنا أو اختره');
        zone.querySelector('.tqa-drop__cta').textContent = cta || TQ.t('استعرض');
        var hintBox = zone.querySelector('.tqa-drop__hint');

        /* الوسم الموروث بعد أن نقل حقله: ما بقي فيه ملصق ومعاينة ونص،
           وثلاثتها صارت داخل الصندوق. وحذفه لا إخفاؤه — عنصر مخفي يبقى
           في ترتيب التنقل بلوح المفاتيح في بعض الوسوم. */
        if (legacy && legacy.parentNode) legacy.parentNode.removeChild(legacy);
        if (pane) {
            var old = pane.querySelector('.tqa-filefield__now');
            if (old && old.parentNode) old.parentNode.removeChild(old);
            var nx = pane.querySelector('[data-tqa-file-preview]');
            if (nx && nx.parentNode) nx.parentNode.removeChild(nx);
        }
        if (clear) {
            /* المربع يخرج من ملصقه ويبقى في النموذج: الملصق نص «احذف
               الصورة الحالية» وقد صار زرا في الصف، والمربع قيمة ترسل. */
            var lw = clear.closest('.tqa-check');
            box.appendChild(clear);
            if (lw && lw.parentNode) lw.parentNode.removeChild(lw);
            clear.classList.add('tqa-drop__clearbox');
        }

        /* سطران للتلميح لا سطر: **الساكن** هو ما كتبته الشاشة في الوسم
           الموروث («المقاس المفضل ‎800 × 500‎»)، و**الوصفي** يشتق من
           `accept` و`data-tqa-max`. والساكن يعرض ما دام الصندوق فارغا
           وحده: نصفه في الشاشات جمل حال («لم تختر ملفا بعد») تصير كذبا
           في اللحظة التي يختار فيها المسؤول ملفا ويراه أمامه. */
        var st = {
            inp: inp, box: box, zone: zone, items: items, err: err,
            hint: hintBox, idle: hint, spec: acceptSay(accept, inp),
            clear: clear, cur: cur, img: wantsImg, urls: [],
            max: parseFloat(inp.getAttribute('data-tqa-max') || '0') || 0
        };
        wire(st);
        paint(st);
    }

    /** «PNG · JPG · WEBP» من `accept` — سطر يقول ما يقبل قبل أن يرفض. */
    function acceptSay(accept, inp) {
        var out = [];
        if (accept) {
            accept.split(',').forEach(function (a) {
                a = a.trim();
                if (!a) return;
                if (a === 'image/*') { out.push('PNG'); out.push('JPG'); out.push('WEBP'); return; }
                if (a.charAt(0) === '.') { out.push(a.slice(1).toUpperCase()); return; }
                var slash = a.indexOf('/');
                if (slash > 0 && a.slice(slash + 1) !== '*') out.push(a.slice(slash + 1).toUpperCase());
            });
        }
        var uniq = out.filter(function (v, i) { return out.indexOf(v) === i; }).slice(0, 5);
        var say = uniq.length ? uniq.join(' · ') : '';
        var mx = parseFloat(inp.getAttribute('data-tqa-max') || '0');
        if (mx) say = say ? TQ.t('____ — حتى ____ ميغابايت', say, mx) : TQ.t('حتى ____ ميغابايت', mx);
        return say;
    }

    /* =====================================================================
       ٣ · الرسم
       ===================================================================== */

    function freeUrls(st) {
        st.urls.forEach(function (u) { try { URL.revokeObjectURL(u); } catch (e) {} });
        st.urls = [];
    }

    function thumb(st, src, icon) {
        var s = document.createElement('span');
        s.className = 'tqa-drop__thumb';
        if (src) {
            var i = document.createElement('img');
            i.alt = '';
            i.loading = 'lazy';
            i.decoding = 'async';
            i.src = src;
            /* ملف ليس صورة أو رابط كسور: الأيقونة أصدق من إطار فارغ. */
            i.addEventListener('error', function () {
                s.textContent = '';
                s.innerHTML = svg(IC.file, 18);
                s.classList.add('is-icon');
            });
            s.appendChild(i);
        } else {
            s.innerHTML = svg(icon || IC.file, 18);
            s.classList.add('is-icon');
        }
        return s;
    }

    function row(cls) {
        var li = document.createElement('li');
        li.className = 'tqa-drop__item' + (cls ? ' ' + cls : '');
        return li;
    }

    function meta(title, sub) {
        var m = document.createElement('span');
        m.className = 'tqa-drop__meta';
        var b = document.createElement('b');
        b.textContent = title;
        m.appendChild(b);
        if (sub) {
            var s = document.createElement('span');
            s.textContent = sub;
            m.appendChild(s);
        }
        return m;
    }

    function iconBtn(cls, icon, label) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'tqa-drop__act ' + cls;
        b.innerHTML = svg(icon, 15) + '<span>' + label + '</span>';
        return b;
    }

    function paint(st) {
        var inp = st.inp;
        freeUrls(st);
        st.items.textContent = '';

        var files = inp.files ? Array.prototype.slice.call(inp.files) : [];
        var gone  = !!(st.clear && st.clear.checked);

        /* ---- المحفوظ ---- */
        if (st.cur && !files.length) {
            var li = row('is-current' + (gone ? ' is-gone' : ''));
            li.appendChild(thumb(st, (st.img || IMG.test(st.cur)) ? st.cur : '', IC.file));
            li.appendChild(meta(
                gone ? TQ.t('سيحذف عند الحفظ') : TQ.t('الملف المحفوظ الآن'),
                gone ? TQ.t('اضغط «تراجع» لتبقيه') : nameOf(st.cur)
            ));

            var acts = document.createElement('span');
            acts.className = 'tqa-drop__acts';

            if (st.clear) {
                var b = gone
                    ? iconBtn('is-undo', IC.undo, TQ.t('تراجع'))
                    : iconBtn('is-danger', IC.trash, TQ.t('احذف'));
                b.addEventListener('click', function () {
                    st.clear.checked = !st.clear.checked;
                    st.clear.dispatchEvent(new Event('change', { bubbles: true }));
                    paint(st);
                });
                acts.appendChild(b);
            }
            li.appendChild(acts);
            st.items.appendChild(li);
        }

        /* ---- المختار الآن ---- */
        files.forEach(function (f, i) {
            var li = row('is-new');
            var src = '';
            if (isImage(f)) {
                src = URL.createObjectURL(f);
                st.urls.push(src);
            }
            li.appendChild(thumb(st, src, IC.file));
            li.appendChild(meta(f.name, size(f.size)));

            var acts = document.createElement('span');
            acts.className = 'tqa-drop__acts';
            var x = iconBtn('is-danger', IC.x, TQ.t('أزل'));
            x.addEventListener('click', function () { drop(st, i); });
            acts.appendChild(x);
            li.appendChild(acts);
            st.items.appendChild(li);
        });

        /* ---- حال الصندوق ---- */
        var full = files.length > 0;
        st.box.classList.toggle('is-full', full || (!!st.cur && !gone));

        st.hint.textContent = full ? st.spec : (st.idle || st.spec);
        st.hint.hidden = !st.hint.textContent;
        st.zone.querySelector('b').textContent = full
            ? (inp.multiple ? TQ.t('أضف ملفا آخر') : TQ.t('اختر ملفا آخر'))
            : (st.cur && !gone
                ? TQ.t('اختر بديلا — أو اسحبه إلى هنا')
                : (inp.multiple ? TQ.t('اسحب الملفات إلى هنا أو اخترها') : TQ.t('اسحب الملف إلى هنا أو اختره')));

        /* اختيار بديل ومحو معا أمران متناقضان، وأحدهما يبطل الآخر صامتا. */
        if (st.clear) {
            st.clear.disabled = full;
            if (full && st.clear.checked) st.clear.checked = false;
        }
    }

    function nameOf(url) {
        var s = String(url).split('?')[0].split('#')[0];
        var i = s.lastIndexOf('/');
        return i >= 0 ? s.slice(i + 1) : s;
    }

    /* =====================================================================
       ٤ · الملفات — إسناد وإزالة

       `input.files` تكتب بـ`DataTransfer` وحدها. والمتصفح الذي لا يعرفها
       يبقى قادرا على المسح كله (`value = ''`) — وهو ما كان الحال قبل هذا
       الملف على كل حال، فلا شيء ينكسر.
       ===================================================================== */

    function DT() {
        try { return new DataTransfer(); } catch (e) { return null; }
    }

    function drop(st, idx) {
        var files = Array.prototype.slice.call(st.inp.files || []);
        var dt = DT();
        if (!dt || files.length <= 1) {
            st.inp.value = '';
        } else {
            files.forEach(function (f, i) { if (i !== idx) dt.items.add(f); });
            st.inp.files = dt.files;
        }
        st.inp.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function take(st, list) {
        var dt = DT();
        if (!dt) return false;
        var keep = st.inp.multiple ? Array.prototype.slice.call(st.inp.files || []) : [];
        keep.forEach(function (f) { dt.items.add(f); });
        for (var i = 0; i < list.length; i++) {
            if (!ok(st, list[i])) return false;
            dt.items.add(list[i]);
            if (!st.inp.multiple) break;
        }
        st.inp.files = dt.files;
        st.inp.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    }

    /** الفحص هنا **قبل** الرفع: خطأ يقال في الشاشة أهون من رفع يرد به الخادم. */
    function ok(st, f) {
        var accept = (st.inp.getAttribute('accept') || '').trim();
        if (accept) {
            var name = (f.name || '').toLowerCase();
            var type = (f.type || '').toLowerCase();
            var hit = accept.split(',').some(function (a) {
                a = a.trim().toLowerCase();
                if (!a) return false;
                if (a.charAt(0) === '.') return name.slice(-a.length) === a;
                if (a.slice(-2) === '/*') return type.indexOf(a.slice(0, -1)) === 0;
                return type === a;
            });
            if (!hit) {
                say(st, TQ.t('هذا الملف بصيغة لا تقبل هنا. المقبول: ____', accept));
                return false;
            }
        }
        if (st.max && f.size > st.max * 1024 * 1024) {
            say(st, TQ.t('حجم الملف ____ ميغابايت، والحد الأقصى ____ ميغابايت.',
                      (f.size / 1048576).toFixed(1), st.max));
            return false;
        }
        say(st, '');
        return true;
    }

    function say(st, msg) {
        st.err.textContent = msg || '';
        st.err.hidden = !msg;
        st.box.classList.toggle('is-bad', !!msg);
    }

    /* =====================================================================
       ٥ · الربط
       ===================================================================== */

    function wire(st) {
        st.zone.addEventListener('click', function (e) {
            e.preventDefault();
            st.inp.click();
        });

        st.inp.addEventListener('change', function () {
            var files = st.inp.files || [];
            /* الفحص يقع على ما اختير من نافذة النظام كذلك، لا على المسحوب
               وحده: `accept` ترشح النافذة ولا تلزمها. */
            for (var i = 0; i < files.length; i++) {
                if (!ok(st, files[i])) { st.inp.value = ''; paint(st); return; }
            }
            if (files.length) say(st, '');
            paint(st);
        });

        ['dragenter', 'dragover'].forEach(function (ev) {
            st.box.addEventListener(ev, function (e) {
                if (!e.dataTransfer || !hasFiles(e.dataTransfer)) return;
                e.preventDefault();
                e.dataTransfer.dropEffect = 'copy';
                st.box.classList.add('is-over');
            });
        });
        ['dragleave', 'dragend'].forEach(function (ev) {
            st.box.addEventListener(ev, function (e) {
                if (e.target !== st.box && st.box.contains(e.relatedTarget)) return;
                st.box.classList.remove('is-over');
            });
        });
        st.box.addEventListener('drop', function (e) {
            if (!e.dataTransfer || !e.dataTransfer.files || !e.dataTransfer.files.length) return;
            e.preventDefault();
            st.box.classList.remove('is-over');
            take(st, e.dataTransfer.files);
        });

        var form = st.inp.form;
        if (form) form.addEventListener('reset', function () { setTimeout(function () { paint(st); }, 0); });
    }

    function hasFiles(dt) {
        if (!dt.types) return true;
        for (var i = 0; i < dt.types.length; i++) if (dt.types[i] === 'Files') return true;
        return false;
    }

    /* =====================================================================
       ٦ · المسح — عند التحميل وعند كل ما يضاف بعده (النوافذ المجلوبة)
       ===================================================================== */

    function scan(root) {
        var box = root || document;
        if (box.querySelectorAll) {
            var list = box.querySelectorAll('input[type=file]');
            for (var i = 0; i < list.length; i++) if (!skip(list[i])) build(list[i]);
        }
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
                    if ((add[j].tagName === 'INPUT' && add[j].type === 'file') ||
                        (add[j].querySelector && add[j].querySelector('input[type=file]'))) {
                        later();
                        return;
                    }
                }
            }
        }).observe(document.documentElement, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();

    TQA.file = { scan: scan };

})(window, document);
