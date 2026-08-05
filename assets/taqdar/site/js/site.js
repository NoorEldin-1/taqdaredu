/* وسمٌ يقول «الجافاسكربت يعمل».
   تُبنى عليه إخفاءات الحركة: ما لم يصل هذا السطر بقي المحتوى مرئيًّا. */
document.documentElement.classList.add('js');

/* منصّة تقدّر — سلوك الموقع.
   ملفّ واحد يخدم الثمان صفحات، وكل كتلة تخرج بهدوء إذا لم تجد عناصرها،
   فالصفحة التي لا تحتوي أكورديون أو فلاتر لا تتأثّر.
   كل ما هو حركي يتوقّف تحت prefers-reduced-motion. */
(function () {
  'use strict';

  var calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  /* نفس عتبة CSS: افتراقهما يُنتج سلوكًا لا يفسّره أحد. */
  var tqMobile = window.matchMedia('(max-width:980px)').matches;
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) {
    return Array.prototype.slice.call((r || document).querySelectorAll(s));
  };

  /* ---------- الهيدر: شفاف فوق الهيرو، ثم يصير سطحًا عند النزول ---------- */
  var header = $('#header');
  if (header) {
    var ticking = false;
    var onScroll = function () {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(function () {
        header.classList.toggle('is-stuck', window.scrollY > 40);
        ticking = false;
      });
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ---------- قائمة الموبايل ---------- */
  var toggle = $('#navToggle');
  var nav = $('#nav');
  if (header && toggle && nav) {
    var setMenu = function (open) {
      header.classList.toggle('is-open', open);
      /* قفل التمرير خلف القائمة: بدونه يمرّر الإصبعُ الصفحةَ تحتها،
         فتُغلق القائمة على محتوًى غير الذي فُتحت فوقه.
         و`position:fixed` على الجسم يقفز بالصفحة إلى أعلاها في iOS،
         فيُحفظ الموضع ويُعاد. */
      var doc = document.documentElement;
      if (open) {
        doc.dataset.tqScroll = String(window.scrollY);
        document.body.style.overflow = 'hidden';
      } else if (doc.dataset.tqScroll !== undefined) {
        document.body.style.overflow = '';
        delete doc.dataset.tqScroll;
      }
      toggle.setAttribute('aria-expanded', String(open));
      toggle.setAttribute('aria-label', open ? 'إغلاق القائمة' : 'فتح القائمة');
      var use = toggle.querySelector('use');
      if (use) use.setAttribute('href', open ? '#i-close' : '#i-menu');
    };

    toggle.addEventListener('click', function () {
      setMenu(toggle.getAttribute('aria-expanded') !== 'true');
    });
    nav.addEventListener('click', function (e) {
      if (e.target.closest('a')) setMenu(false);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && header.classList.contains('is-open')) setMenu(false);
    });
  }

  /* ---------- دخول العناصر: 320ms مع تتابع 60ms داخل المجموعة الواحدة ---------- */
  var items = $$('.reveal');

  /* TQ-CAROUSEL-SKIP — بطاقات المسار الأفقيّ تظهر فورًا.
     مستطيل التقاطع يُقصّ بالحاويات ذات `overflow`، فالبطاقة الخارجة
     أفقيًّا **لا تتقاطع أبدًا** وتبقى شفّافة حتى يسحبها المستخدم — ثمّ
     تظهر متأخّرة. فالمراقِب هنا لا يؤخّر الظهور، بل يمنعه. */
  items = items.filter(function (el) {
    if (el.closest('.carousel')) { el.classList.add('is-in'); return false; }
    return true;
  });

  if (calm || tqMobile || !('IntersectionObserver' in window)) {
    items.forEach(function (el) { el.classList.add('is-in'); });
  } else {
    // التتابع يُحسب داخل الحاوية المباشرة، فصفّ الكروت يتدرّج ولا تتدرّج الصفحة كلّها.
    var seen = new WeakMap();
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var el = entry.target;
        var parent = el.parentElement;
        var n = seen.get(parent) || 0;
        seen.set(parent, n + 1);
        el.style.transitionDelay = Math.min(n, 3) * 45 + 'ms';
        el.classList.add('is-in');
        io.unobserve(el);
      });
    }, { rootMargin: '0px 0px 18% 0px', threshold: 0 });

    items.forEach(function (el) { io.observe(el); });
  }

  /* ---------- باراللاكس خفيف جدًّا على الفوانيس والهالات ---------- */
  if (!calm) {
    var floaters = $$('.lantern, .bg-glow');
    if (floaters.length && !tqMobile) {
      var raf = false;
      window.addEventListener('scroll', function () {
        if (raf) return;
        raf = true;
        requestAnimationFrame(function () {
          var y = window.scrollY;
          floaters.forEach(function (el, i) {
            // ≤ 8px إزاحة: يُحسّ ولا يُلاحَظ.
            el.style.setProperty('--drift', (y * (i % 2 ? 0.014 : -0.011)).toFixed(2) + 'px');
            el.style.translate = '0 var(--drift)';
          });
          raf = false;
        });
      }, { passive: true });
    }
  }

  /* ---------- عدّادات الأرقام ---------- */
  var counters = $$('[data-count]');
  if (counters.length) {
    var fmt = function (n) { return n.toLocaleString('en-US'); };
    var run = function (el) {
      var target = parseFloat(el.dataset.count);
      var pre = el.dataset.prefix || '';
      var post = el.dataset.suffix || '';
      if (calm) { el.textContent = pre + fmt(target) + post; return; }
      var start = performance.now();
      var dur = 1100;
      var step = function (now) {
        var t = Math.min(1, (now - start) / dur);
        // easeOutCubic — يبدأ سريعًا ويستقرّ، فالرقم يُقرأ قبل أن يتوقّف
        var v = target * (1 - Math.pow(1 - t, 3));
        el.textContent = pre + fmt(Math.round(v)) + post;
        if (t < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    };

    if (!('IntersectionObserver' in window)) {
      counters.forEach(run);
    } else {
      var cio = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          if (!e.isIntersecting) return;
          run(e.target);
          cio.unobserve(e.target);          // مرّة واحدة فقط
        });
      }, { threshold: 0.4 });
      counters.forEach(function (el) { cio.observe(el); });
    }
  }

  /* ---------- الأسئلة الشائعة: أكورديون ---------- */
  var faq = $('#faq');
  if (faq) {
    faq.addEventListener('click', function (e) {
      var q = e.target.closest('.faq-q');
      if (!q) return;
      var item = q.closest('.faq-item');
      var open = !item.classList.contains('is-open');
      item.classList.toggle('is-open', open);
      q.setAttribute('aria-expanded', String(open));
    });
  }

  /* ---------- الكاروسل ----------
     مزلقان مقيسان في RTL:
     1) `scrollBy({left})` بالبكسل الفيزيائي لا المنطقي — المحتوى ممتدّ يسارًا،
        فالتقدّم يحتاج قيمة **سالبة**. لذلك ضربُ الخطوة في إشارة الاتجاه.
     2) `scroll-behavior:smooth` في CSS يبتلع scrollBy البرمجي مع scroll-snap
        (قيست: صفر حركة). فالسلاسة تُمرَّر في الاستدعاء لا في الورقة.
     و`scrollLeft` نفسه سالب، فحساب الأطراف بالقيمة المطلقة. */
  $$('.carousel').forEach(function (car) {
    var track = $('.carousel__track, .grid-4, .grid-5', car);
    var nav = $('.carousel__nav', car);
    if (!track || !nav) return;
    var prev = $('[data-dir="prev"]', nav);
    var next = $('[data-dir="next"]', nav);
    var sign = getComputedStyle(track).direction === 'rtl' ? -1 : 1;
    var glide = calm ? 'auto' : 'smooth';

    var step = function () {
      var card = track.querySelector(':scope > *:not([hidden])');
      var gap = parseFloat(getComputedStyle(track).columnGap) || 0;
      return card ? (card.getBoundingClientRect().width + gap) * 2 : track.clientWidth * 0.8;
    };

    /* هامش الطرف: `scroll-snap` يستقرّ عند |scrollLeft|=5 لا 0 (قيست) بسبب
       حشوة المسار، فعتبة 4px كانت تُبقي «السابق» مفعّلًا في البداية. و12 تظلّ
       أصغر بكثير من خطوة البطاقة (≈610px) فلا تبتلع تمريرة حقيقية. */
    var EDGE = 12;
    var sync = function () {
      var max = track.scrollWidth - track.clientWidth;
      var at = Math.abs(track.scrollLeft);
      // الأسهم بلا معنى إن كان المحتوى يسع المسار
      nav.hidden = max < EDGE;
      if (prev) prev.disabled = at < EDGE;
      if (next) next.disabled = at > max - EDGE;
    };

    if (prev) prev.addEventListener('click', function () {
      track.scrollBy({ left: -sign * step(), behavior: glide });
    });
    if (next) next.addEventListener('click', function () {
      track.scrollBy({ left: sign * step(), behavior: glide });
    });

    track.addEventListener('scroll', sync, { passive: true });
    window.addEventListener('resize', sync);
    // بعد الفلترة يتغيّر عدد البطاقات فيتغيّر scrollWidth
    car.addEventListener('tq:filtered', sync);
    sync();
  });

  /* ---------- الكتالوج: فئة واحدة تفلتر المواد والكتب فعليًّا ----------
     كان الاختيار سابقًا يبدّل نصّ العنوان فقط ولا يمسّ أي بطاقة. الفلترة الآن
     على البيانات التي يولّدها البنّاء من catalog.json (data-cat على كل بطاقة). */
  var catPicker = $('#catPicker');
  if (catPicker) {
    var catTitle = $('#catalogTitle');

    var filterGrid = function (gridSel, emptySel, cat) {
      var grid = $(gridSel);
      if (!grid) return;
      var shown = 0;
      $$('[data-cat]', grid).forEach(function (card) {
        var ok = !cat || card.dataset.cat === cat;
        card.hidden = !ok;
        if (ok) shown++;
      });
      var empty = $(emptySel);
      if (empty) empty.hidden = shown !== 0;
      var car = grid.closest('.carousel');
      if (car) {
        car.dispatchEvent(new CustomEvent('tq:filtered'));
        grid.scrollTo({ left: 0 });
      }
    };

    // نفس المنتقي يخدم صفحتَي المسارات والكتب؛ العنوان يتبع الشبكة الموجودة فعلًا
    var noun = $('#bookGrid') && !$('#materialGrid') ? 'كتب ' : 'مواد ';
    var allLabel = noun === 'كتب ' ? 'جميع الكتب' : 'جميع المواد والمسارات';

    var applyCat = function (cat, label) {
      filterGrid('#materialGrid', '#materialEmpty', cat);
      filterGrid('#bookGrid', '#bookEmpty', cat);
      if (catTitle) catTitle.textContent = cat ? noun + label : allLabel;
    };

    var preset = new URLSearchParams(location.search).get('cat');
    if (preset) {
      var target = $$('.stage-card', catPicker).filter(function (c) {
        return c.dataset.cat === preset;
      })[0];
      if (target) {
        $$('.stage-card', catPicker).forEach(function (c) {
          c.setAttribute('aria-pressed', String(c === target));
        });
        applyCat(preset, (target.querySelector('b') || {}).textContent || '');
      }
    }

    catPicker.addEventListener('click', function (e) {
      var card = e.target.closest('.stage-card');
      if (!card) return;
      $$('.stage-card', catPicker).forEach(function (c) {
        c.setAttribute('aria-pressed', String(c === card));
      });
      applyCat(card.dataset.cat, (card.querySelector('b') || {}).textContent || '');

      /* النتيجة المفلترة تصير عنوانًا يُشارَك ويُحفظ وينجو من التحديث.
         القارئ يقرأ `?cat=` أصلًا عند التحميل — فكان نصف الطريق مبنيًّا
         والنصف الآخر مفقودًا. و`replaceState` لا `pushState`: الفلترة
         ليست صفحةً جديدة، وزرّ الرجوع يجب أن يخرج من الصفحة لا أن
         يتراجع خطوةً خطوةً في المرشّحات. */
      var u = new URL(location.href);
      if (card.dataset.cat) u.searchParams.set('cat', card.dataset.cat);
      else u.searchParams.delete('cat');
      history.replaceState(null, '', u);
    });
  }

  /* ---------- المعلمون: بحث وفلترة وفرز ---------- */
  var teacherGrid = $('#teacherGrid');
  if (teacherGrid) {
    var cards = $$('.teacher-card', teacherGrid);
    var empty = $('#teacherEmpty');
    var search = $('#teacherSearch');
    var headerSearch = $('#headerTeacherSearch');
    var stageSel = $('#teacherStage');
    var sortSel = $('#teacherSort');

    var apply = function () {
      var q = ((search && search.value) || '').trim();
      var stage = (stageSel && stageSel.value) || '';
      var shown = 0;

      cards.forEach(function (c) {
        var ok = (!q || (c.dataset.search || c.dataset.name).indexOf(q) !== -1) &&
                 (!stage || c.dataset.stage === stage);
        c.hidden = !ok;
        if (ok) shown++;
      });
      if (empty) empty.hidden = shown !== 0;

      if (sortSel) {
        var key = sortSel.value;
        cards.slice()
          .sort(function (a, b) {
            return parseFloat(b.dataset[key === 'rating' ? 'rating' :
                   key === 'reviews' ? 'reviews' : 'courses']) -
                   parseFloat(a.dataset[key === 'rating' ? 'rating' :
                   key === 'reviews' ? 'reviews' : 'courses']);
          })
          .forEach(function (c) { teacherGrid.appendChild(c); });
      }
    };

    [search, stageSel, sortSel].forEach(function (el) {
      if (el) el.addEventListener('input', apply);
    });
    // البحث في الهيدر يغذّي حقل الدليل نفسه بدل أن يكون فلترًا ثانيًا مستقلًّا
    if (headerSearch && search) {
      headerSearch.addEventListener('input', function () {
        search.value = headerSearch.value;
        apply();
      });
    }
  }

  /* ---------- المدوّنة: تصنيفات وبحث ---------- */
  var postGrid = $('#postGrid');
  if (postGrid) {
    var posts = $$('[data-cat]').filter(function (el) {
      return el.classList.contains('post-card') || el.classList.contains('post-row') ||
             el.classList.contains('feat-post');
    });
    var postEmpty = $('#postEmpty');
    var postSearch = $('#postSearch');
    var catLinks = $$('#catNav a').concat($$('.side-list a[data-cat]'));
    var activeCat = '';

    var applyPosts = function () {
      var q = ((postSearch && postSearch.value) || '').trim();
      var shown = 0;
      posts.forEach(function (el) {
        var ok = (!activeCat || el.dataset.cat === activeCat) &&
                 (!q || (el.dataset.title || '').indexOf(q) !== -1);
        el.hidden = !ok;
        if (ok) shown++;
      });
      if (postEmpty) postEmpty.hidden = shown !== 0;
    };

    catLinks.forEach(function (a) {
      a.addEventListener('click', function () {
        activeCat = a.dataset.cat || '';
        $$('#catNav a').forEach(function (n) {
          n.classList.toggle('is-active', (n.dataset.cat || '') === activeCat);
        });
        applyPosts();
      });
    });
    if (postSearch) postSearch.addEventListener('input', applyPosts);
  }

  /* ---------- النماذج: تحقّق محلّي ثم حالة نجاح ---------- */
  $$('form[data-validate]').forEach(function (form) {
    var ok = form.parentElement.querySelector('[data-ok]');

    form.addEventListener('submit', function (e) {
      /* TQ-GATE — المنع يُقرَّر بعد التحقّق لا قبله.
         كان هنا منعٌ غير مشروط من مرحلة العرض التصميميّ، وبقي بعد
         ربط النموذج بالخادم — فكل رسالة تُكتب تُمسح ويُقال لصاحبها
         «تمّ استلام رسالتك». الترتيب الصحيح أدناه. */
      var bad = null;

      $$('input, textarea, select', form).forEach(function (f) {
        if (!f.name) return;
        var value = f.value.trim();
        var valid = f.required ? value !== '' : true;
        if (valid && f.type === 'email') valid = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value);
        if (valid && f.type === 'tel' && value) valid = /^5[0-9]{8}$/.test(value);

        var field = f.closest('.form-field');
        if (field) field.classList.toggle('form-field--invalid', !valid);
        if (!valid && !bad) bad = f;
      });

      if (bad) { e.preventDefault(); bad.focus(); return; }

      /* نموذجٌ له وجهة يمضي إليها؛ والتزييف لنموذج العرض وحده. */
      if (form.getAttribute('action')) { return; }
      e.preventDefault();
      form.reset();
      if (ok) {
        ok.classList.add('is-on');
        setTimeout(function () { ok.classList.remove('is-on'); }, 6000);
      }
    });

    // يختفي التحذير بمجرّد أن يبدأ المستخدم في التصحيح
    form.addEventListener('input', function (e) {
      var field = e.target.closest('.form-field');
      if (field) field.classList.remove('form-field--invalid');
    });
  });
})();

/* ---- فيديو الهيرو ---------------------------------------------------
   يعمل على الجوّال والحاسوب معًا (طلب المالك). والثمن يُخفَّض لا يُدفع
   كاملًا: نسخةٌ بعرض 720 (471 ك.ب) دون 980px، وبعرض 1280 (1.3 م.ب) فوقها.
   ويُحترم `prefers-reduced-motion` ووضع توفير البيانات — الأوّل تفضيلٌ
   صحّيّ لا ذوقيّ، والثاني إعلانٌ صريح بأنّ البايت يُحاسَب عليه. */
(function () {
  var v = document.querySelector('[data-tq-hero-video]');
  if (!v) return;
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  if (navigator.connection && navigator.connection.saveData) return;

  var small = window.matchMedia('(max-width: 980px)').matches;
  var base = v.getAttribute('poster');
  /* WebM أوّلًا وMP4 بديلًا: سفاري القديم وبعض أجهزة iOS لا تفكّ WebM،
     فكانت الخلفية تبقى صورةً ساكنة بلا سبب ظاهر. و`canPlayType` تسأل
     المتصفّح بدل أن نخمّن عنه. */
  var webm = base.replace('hero-poster.webp', small ? 'hero-sm.webm' : 'hero.webm');
  var mp4  = base.replace('hero-poster.webp', small ? 'hero-sm.mp4' : 'hero.mp4');
  v.src = v.canPlayType('video/webm') ? webm : mp4;
  v.addEventListener('error', function () {
    if (v.src.indexOf('.webm') !== -1) { v.src = mp4; v.play().catch(function () {}); }
  });
  v.addEventListener('playing', function () {
    v.classList.add('is-on');
    var t = document.querySelector('[data-tq-hero-toggle]');
    if (t) {
      t.hidden = false;
      t.addEventListener('click', function () {
        var off = !v.paused;
        if (off) v.pause(); else v.play().catch(function () {});
        t.setAttribute('aria-label', off ? 'تشغيل الخلفية المتحركة' : 'إيقاف الخلفية المتحركة');
        var u = t.querySelector('use');
        if (u) u.setAttribute('href', off ? '#i-play' : '#i-close');
      });
    }
  }, { once: true });

  function play() { var g = v.play(); if (g && g.catch) g.catch(function () {}); }
  play();

  /* خارج الشاشة لا يُشغَّل: إطاراتٌ لا تُرى تستهلك بطاريةً بلا مقابل،
     وهو على الجوّال أهمّ منه على الحاسوب. */
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (es) {
      es.forEach(function (e) { e.isIntersecting ? play() : v.pause(); });
    }, { threshold: 0.05 }).observe(v);
  }
})();

/* ---- الكاروسل ------------------------------------------------------
   السحب والزخم وحدّ التمرير من المتصفّح عبر `scroll-snap`. وهذا هنا
   للأزرار وتعطيلها عند الطرفين — وفي RTL يكون `scrollLeft` سالبًا في
   المتصفّحات الحديثة، فالحساب بالمقدار لا بالإشارة. */
(function () {
  var boxes = document.querySelectorAll('[data-tq-carousel]');
  if (!boxes.length) return;

  Array.prototype.forEach.call(boxes, function (box) {
    var track = box.querySelector('[data-tq-car-track]');
    var prev  = box.querySelector('[data-tq-car-prev]');
    var next  = box.querySelector('[data-tq-car-next]');
    if (!track) return;

    function step() {
      var first = track.firstElementChild;
      if (!first) return track.clientWidth * 0.8;
      var gap = parseFloat(getComputedStyle(track).columnGap || getComputedStyle(track).gap) || 0;
      return first.getBoundingClientRect().width + gap;
    }
    function go(dir) { track.scrollBy({ left: dir * step(), behavior: 'smooth' }); }

    if (prev) prev.addEventListener('click', function () { go(1); });
    if (next) next.addEventListener('click', function () { go(-1); });

    function sync() {
      var max = track.scrollWidth - track.clientWidth;
      var at  = Math.abs(track.scrollLeft);          /* RTL: سالب في الحديث */
      if (prev) prev.disabled = at <= 2;
      if (next) next.disabled = at >= max - 2;
    }
    track.addEventListener('scroll', sync, { passive: true });
    addEventListener('resize', sync);
    sync();
  });
})();

/* ---- تبويب المرحلة --------------------------------------------------
   البطاقات كلّها في الصفحة ويُخفى ما لا يخصّ المرحلة — فالتبديل فوريّ
   بلا طلب. والخادم يُخفي غير الافتراضيّة أصلًا، فلو فشل هذا السكربت
   رأى الزائر مرحلةً واحدة صحيحة لا ستّ بطاقاتٍ مختلطة. */
(function () {
  var tabs = document.querySelectorAll('[data-tq-stage]');
  if (!tabs.length) return;
  var cards = document.querySelectorAll('[data-tq-bundles] [data-stage]');

  function pick(stage) {
    Array.prototype.forEach.call(cards, function (c) {
      c.hidden = (c.getAttribute('data-stage') !== stage);
    });
    Array.prototype.forEach.call(tabs, function (t) {
      var on = (t.getAttribute('data-tq-stage') === stage);
      t.classList.toggle('is-on', on);
      t.setAttribute('aria-selected', on ? 'true' : 'false');
    });
  }

  Array.prototype.forEach.call(tabs, function (t) {
    t.addEventListener('click', function () { pick(t.getAttribute('data-tq-stage')); });
    /* الأسهم تتنقّل بين التبويبات كما يتوقّع قارئ الشاشة */
    t.addEventListener('keydown', function (e) {
      if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
      e.preventDefault();
      var arr = Array.prototype.slice.call(tabs);
      var i = arr.indexOf(t);
      var d = (e.key === 'ArrowLeft') ? 1 : -1;   /* RTL: اليسار يتقدّم */
      var n = arr[(i + d + arr.length) % arr.length];
      n.focus(); n.click();
    });
  });

  /* ---------- المرساة: تُفتح المرحلة ثمّ يُمرَّر ----------
     ثلاث مشكلات في رابطٍ واحد مثل `/plans#plus-middle`:
       · الهدف قد يكون `hidden` — تبويب المرحلة يُخفي ما ليس مرحلته،
         فثلاثٌ من روابط التسعير الستّ تشير إلى عناصر لا تُرسم أصلًا.
       · والمتصفّح يُمرّر عند التحليل، ثمّ تُغيّر الصورُ المتأخّرة التخطيط
         فيبقى الموضع خاطئًا — قِستُ `scrollY=0` والهدف عند ٤٩٨px.
       · و`scroll-margin` وحدها لا تُصلح ما لم يقع تمريرٌ أصلًا.
     فيُؤخَّر التمرير إلى ما بعد استقرار التخطيط، وتُفتح المرحلة قبله. */
  var tqAnchor = function () {
    var id = decodeURIComponent((location.hash || '').slice(1));
    if (!id) return;
    var el = document.getElementById(id);
    if (!el) return;

    var stage = el.getAttribute('data-stage');
    if (stage) {
      var tab = document.querySelector('[data-tq-stage="' + stage + '"]');
      if (tab) tab.click();
    }
    var jump = function () { el.scrollIntoView({ block: 'start', behavior: 'instant' }); };
    requestAnimationFrame(function () { setTimeout(jump, 60); });
    /* تصحيحٌ واحد بعد استقرار الصور: القفزة الأولى تضع الزائر في المكان،
       وصورةٌ كسولة تصل بعدها تُزيحه — فيُعاد الضبط مرّةً لا حلقةً. */
    setTimeout(jump, 700);
  };
  window.addEventListener('load', tqAnchor);
  window.addEventListener('hashchange', tqAnchor);


  /* ---------- إظهار كلمة المرور ----------
     الحقل المخفيّ لا يقول لصاحبه أخطأ في حرفٍ أم في لغة لوحة المفاتيح.
     والزرّ يبدّل النوع ويُعلن حالته لقارئ الشاشة بـ`aria-pressed`. */
  document.querySelectorAll('[data-tq-pw]').forEach(function (btn) {
    var inp = document.getElementById(btn.getAttribute('data-tq-pw'));
    if (!inp) return;
    btn.addEventListener('click', function () {
      var show = inp.type === 'password';
      inp.type = show ? 'text' : 'password';
      btn.setAttribute('aria-pressed', String(show));
      btn.setAttribute('aria-label', show ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور');
      var u = btn.querySelector('use');
      if (u) u.setAttribute('href', show ? '#i-close' : '#i-eye');
      inp.focus();
    });
  });

  /* ---------- نسخ بيانات التحويل ----------
     الآيبان أربعةٌ وعشرون محرفًا يُملى بالعين من شاشةٍ إلى تطبيق بنك:
     محرفٌ واحد يخطئ فترتدّ الحوالة، ولا يعرف الطالب لماذا.

     و`navigator.clipboard` يشترط سياقًا آمنًا وقد يرفض بلا استثناء
     يُلتقَط — فله بديلٌ قديم يعمل حيث لا يعمل. والفشل يُقال صراحةً:
     زرٌّ يبتلع الضغطة صامتًا يجعل المستخدم يظنّ أنّه نسخ. */
  var tqCopy = function (text, done) {
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(function () { done(true); },
                                              function () { done(false); });
      return;
    }
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', '');
    ta.style.position = 'fixed';
    ta.style.insetInlineStart = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    var ok = false;
    try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
    document.body.removeChild(ta);
    done(ok);
  };

  document.querySelectorAll('[data-tq-copy]').forEach(function (btn) {
    var label = btn.getAttribute('aria-label') || 'نسخ';
    btn.addEventListener('click', function () {
      tqCopy(btn.getAttribute('data-tq-copy'), function (ok) {
        btn.classList.toggle('is-done', ok);
        btn.setAttribute('aria-label', ok ? 'نُسخ' : 'تعذّر النسخ — حدّده وانسخه يدويًّا');
        var u = btn.querySelector('use');
        if (u) u.setAttribute('href', ok ? '#i-check' : '#i-close');
        setTimeout(function () {
          btn.classList.remove('is-done');
          btn.setAttribute('aria-label', label);
          if (u) u.setAttribute('href', '#i-copy');
        }, 2000);
      });
    });
  });

})();
