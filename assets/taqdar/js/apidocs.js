/* ==========================================================================
   وثائق واجهة البرمجة — سلوك الصفحة.

   خمسة أشياء لا أكثر: تبويب اللغة، وإبراز الشيفرة، والبحث، وتتبع القسم
   الحالي، والنسخ. بلا مكتبة ولا اعتماد خارجي — الصفحة تفتح كاملة بلا
   جافاسكربت أصلا، وهذا الملف يحسنها ولا تقوم به.
   ========================================================================== */

(function () {
    'use strict';

    var $  = function (s, r) { return (r || document).querySelector(s); };
    var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

    /* ======================================================================
       ١ · إبراز الشيفرة

       مبرز صغير يفهم أربع لغات نكتبها نحن، لا محلل عام.

       والقاعدة الوحيدة التي لا تخرق: **لا يبنى HTML من نص لم يهرب.** كل
       رمز يمر بـ`esc()` قبل أن يلف بوسم، فقيمة في المواصفة فيها `<script>`
       تبقى نصا يقرأ ولا تصير وسما ينفذ. والدخل هنا من مواصفتنا لا من
       المستخدم، ولكن الوثيقة تنشر علنا ولا يبنى أمان على «الدخل موثوق».
       ====================================================================== */

    function esc(s) {
        return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    /* العربية تعزل داخل الشيفرة: بلا `bdi` تقفز الفاصلة والقوس إلى الطرف
       الخطأ في السطر الذي فيه نص عربي، فيقرأ JSON مكسورا وهو سليم. */
    var ARABIC = /([؀-ۿ][؀-ۿ\s،؛؟.,!?%0-9«»—–-]*)/g;
    function bidi(s) { return s.replace(ARABIC, '<bdi>$1</bdi>'); }

    var KEYWORDS = {
        dart: /\b(await|async|final|const|class|factory|return|if|else|for|while|try|catch|finally|throw|new|late|var|void|import|extends|implements|get|set|static|this|super|true|false|null|Future|String|int|double|bool|Map|List)\b/g,
        js:   /\b(await|async|const|let|var|function|return|if|else|for|while|try|catch|finally|throw|new|class|export|import|from|true|false|null|undefined|typeof)\b/g
    };

    function highlight(code, lang) {
        var src = code.textContent;
        var out;

        if (lang === 'json') {
            out = esc(src)
                /* المفتاح قبل القيمة: النمطان يتشابهان، والترتيب هو ما
                   يفرقهما — فالمفتاح وحده متبوع بنقطتين. */
                .replace(/(&quot;(?:[^&]|&(?!quot;))*?&quot;)(\s*:)/g,
                         '<span class="tok-key">$1</span><span class="tok-pun">$2</span>')
                .replace(/:\s*(&quot;(?:[^&]|&(?!quot;))*?&quot;)/g,
                         ': <span class="tok-str">$1</span>')
                .replace(/\b(-?\d+\.?\d*)\b(?![^<]*<\/span>)/g, '<span class="tok-num">$1</span>')
                .replace(/\b(true|false|null)\b(?![^<]*<\/span>)/g, '<span class="tok-kw">$1</span>');

        } else if (lang === 'bash') {
            out = esc(src)
                .replace(/(#[^\n]*)/g, '<span class="tok-com">$1</span>')
                .replace(/(&#039;[^&]*?&#039;|&quot;[^&]*?&quot;)/g, '<span class="tok-str">$1</span>')
                .replace(/^(curl)\b/gm, '<span class="tok-kw">$1</span>')
                .replace(/(\s)(-[A-Za-z-]+)\b/g, '$1<span class="tok-key">$2</span>')
                .replace(/(\$[A-Z_a-z][A-Za-z0-9_]*)/g, '<span class="tok-num">$1</span>');

        } else if (lang === 'dart' || lang === 'js') {
            out = esc(src)
                .replace(/(\/\/[^\n]*)/g, '<span class="tok-com">$1</span>')
                .replace(/(&#039;[^&\n]*?&#039;|&quot;[^&\n]*?&quot;)/g, '<span class="tok-str">$1</span>')
                .replace(KEYWORDS[lang], '<span class="tok-kw">$1</span>')
                .replace(/\b(\d+)\b/g, '<span class="tok-num">$1</span>');
        } else {
            out = esc(src);
        }

        code.innerHTML = bidi(out);
    }

    $$('pre code').forEach(function (c) {
        var m = /lang-([a-z]+)/.exec(c.className);
        if (m) { try { highlight(c, m[1]); } catch (e) { /* نص عاد أفضل من صفحة بيضاء */ } }
    });

    /* ======================================================================
       ٢ · تبويب اللغة — الاختيار عام لا لكل صندوق

       من اختار Dart لا يريد أن يختاره ثلاثين مرة وهو ينزل الصفحة. والاختيار
       يحفظ فيبقى بين الزيارات.
       ====================================================================== */

    var LANG_KEY = 'tq.apidocs.lang';

    function setLang(lang) {
        $$('.panel').forEach(function (p) {
            var tabs  = $$('.tab', p);
            var panes = $$('.panel__pre[data-pane]', p);
            if (!tabs.length || !panes.length) return;

            /* لغة غير معروضة في هذا الصندوق تسقط إلى أول تبويب فيه، ولا
               يترك الصندوق فارغا. */
            var has = tabs.some(function (t) { return t.dataset.lang === lang; });
            var pick = has ? lang : tabs[0].dataset.lang;

            tabs.forEach(function (t) { t.classList.toggle('is-on', t.dataset.lang === pick); });
            panes.forEach(function (n) { n.hidden = (n.dataset.pane !== pick); });
        });

        try { localStorage.setItem(LANG_KEY, lang); } catch (e) {}
    }

    document.addEventListener('click', function (e) {
        var tab = e.target.closest('.tab');
        if (tab) setLang(tab.dataset.lang);
    });

    try {
        var saved = localStorage.getItem(LANG_KEY);
        if (saved) setLang(saved);
    } catch (e) {}

    /* ======================================================================
       ٣ · النسخ
       ====================================================================== */

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.copy');
        if (!btn) return;

        var text = btn.dataset.copy;
        if (!text) {
            var panel = btn.closest('.panel');
            var pane  = panel && $$('.panel__pre', panel).filter(function (n) { return !n.hidden; })[0];
            text = pane ? pane.textContent : '';
        }
        if (!text) return;

        var done = function () {
            var was = btn.textContent;
            btn.textContent = 'copied';
            btn.classList.add('is-done');
            setTimeout(function () { btn.textContent = was; btn.classList.remove('is-done'); }, 1400);
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done, function () {});
            return;
        }
        /* الحافظة الحديثة لا تعمل على http، والوثائق تقرأ محليا كثيرا. */
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;top:-9999px';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(); } catch (err) {}
        document.body.removeChild(ta);
    });

    /* ======================================================================
       ٤ · البحث في القائمة
       ====================================================================== */

    var search = $('#navsearch');
    if (search) {
        search.addEventListener('input', function () {
            var q = search.value.trim().toLowerCase();

            $$('.navlink[data-search]').forEach(function (a) {
                a.hidden = q !== '' && a.dataset.search.indexOf(q) === -1;
            });

            /* عنوان مجموعة لم يبق تحته بند يخفى معها: ترويسة فوق فراغ
               تجعل البحث يبدو معطوبا. */
            $$('.navgroup').forEach(function (h) {
                var any = false, n = h.nextElementSibling;
                while (n && n.classList.contains('navlink')) {
                    if (!n.hidden) { any = true; break; }
                    n = n.nextElementSibling;
                }
                h.hidden = !any;
            });
        });

        /* الشرطة المائلة تنقل إلى البحث — عرف في وثائق الواجهات. */
        document.addEventListener('keydown', function (e) {
            if (e.key === '/' && !/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) {
                e.preventDefault();
                search.focus();
                search.select();
            }
            if (e.key === 'Escape' && document.activeElement === search) {
                search.value = '';
                search.dispatchEvent(new Event('input'));
                search.blur();
            }
        });
    }

    /* ======================================================================
       ٥ · تتبع القسم الحالي

       `IntersectionObserver` لا مستمع تمرير: الثاني ينادى مئات المرات في
       الثانية على قائمة من ثلاثين رابطا.
       ====================================================================== */

    var links = {};
    $$('.side__nav a[href^="#"]').forEach(function (a) { links[a.getAttribute('href').slice(1)] = a; });

    var targets = $$('.sec[id]');
    if (targets.length && 'IntersectionObserver' in window) {
        var seen = {};

        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) { seen[en.target.id] = en.isIntersecting; });

            var current = targets.filter(function (t) { return seen[t.id]; })[0];
            if (!current) return;

            $$('.side__nav a.is-on').forEach(function (a) { a.classList.remove('is-on'); });

            var link = links[current.id];
            if (!link) return;
            link.classList.add('is-on');

            /* الرابط النشط يبقى مرئيا في قائمة تطول عن الشاشة. */
            var nav = link.parentNode;
            var top = link.offsetTop - nav.scrollTop;
            if (top < 0 || top > nav.clientHeight - 60) {
                link.scrollIntoView({ block: 'nearest' });
            }
        }, { rootMargin: '-72px 0px -55% 0px', threshold: 0 });

        targets.forEach(function (t) { io.observe(t); });
    }

    /* ======================================================================
       ٦ · القائمة على الشاشات الضيقة
       ====================================================================== */

    var toggle = $('#navtoggle');
    var side   = $('#side');

    if (toggle && side) {
        var close = function () {
            side.classList.remove('is-open');
            document.body.classList.remove('nav-open');
            toggle.setAttribute('aria-expanded', 'false');
        };

        toggle.addEventListener('click', function () {
            var open = side.classList.toggle('is-open');
            document.body.classList.toggle('nav-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        /* اختيار بند يغلق القائمة: تركها مفتوحة فوق المحتوى يخفي ما ذهب
           القارئ إليه. */
        side.addEventListener('click', function (e) {
            if (e.target.closest('a[href^="#"]')) close();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') close();
        });
    }
})();
