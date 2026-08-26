/* وسم يقول «الجافاسكربت يعمل».
   تبنى عليه إخفاءات الحركة: ما لم يصل هذا السطر بقي المحتوى مرئيا. */
document.documentElement.classList.add('js');

/* منصة تقدر — سلوك الموقع.
   ملف واحد يخدم الثمان صفحات، وكل كتلة تخرج بهدوء إذا لم تجد عناصرها،
   فالصفحة التي لا تحتوي أكورديون أو فلاتر لا تتأثر.
   كل ما هو حركي يتوقف تحت prefers-reduced-motion. */
(function () {
  'use strict';

  var calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  /* نفس عتبة CSS: افتراقهما ينتج سلوكا لا يفسره أحد. */
  var tqMobile = window.matchMedia('(max-width:980px)').matches;
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) {
    return Array.prototype.slice.call((r || document).querySelectorAll(s));
  };

  /* ---------- الهيدر: شفاف فوق الهيرو، ثم يصير سطحا عند النزول ---------- */
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
      /* قفل التمرير خلف القائمة: بدونه يمرر الإصبع الصفحة تحتها،
         فتغلق القائمة على محتوى غير الذي فتحت فوقه.
         و`position:fixed` على الجسم يقفز بالصفحة إلى أعلاها في iOS،
         فيحفظ الموضع ويعاد. */
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

  /* TQ-CAROUSEL-SKIP — بطاقات المسار الأفقي تظهر فورا.
     مستطيل التقاطع يقص بالحاويات ذات `overflow`، فالبطاقة الخارجة
     أفقيا **لا تتقاطع أبدا** وتبقى شفافة حتى يسحبها المستخدم — ثم
     تظهر متأخرة. فالمراقب هنا لا يؤخر الظهور، بل يمنعه. */
  items = items.filter(function (el) {
    if (el.closest('.carousel, .carousel2')) { el.classList.add('is-in'); return false; }
    return true;
  });

  if (calm || tqMobile || !('IntersectionObserver' in window)) {
    items.forEach(function (el) { el.classList.add('is-in'); });
  } else {
    // التتابع يحسب داخل الحاوية المباشرة، فصف الكروت يتدرج ولا تتدرج الصفحة كلها.
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

  /* ---------- باراللاكس خفيف جدا على الفوانيس والهالات ---------- */
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
            // ≤ 8px إزاحة: يحس ولا يلاحظ.
            el.style.setProperty('--drift', (y * (i % 2 ? 0.014 : -0.011)).toFixed(2) + 'px');
            el.style.translate = '0 var(--drift)';
          });
          raf = false;
        });
      }, { passive: true });
    }
  }

  /* ---------- عدادات الأرقام ---------- */
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
        // easeOutCubic — يبدأ سريعا ويستقر، فالرقم يقرأ قبل أن يتوقف
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
          cio.unobserve(e.target);          // مرة واحدة فقط
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
     1) `scrollBy({left})` بالبكسل الفيزيائي لا المنطقي — المحتوى ممتد يسارا،
        فالتقدم يحتاج قيمة **سالبة**. لذلك ضرب الخطوة في إشارة الاتجاه.
     2) `scroll-behavior:smooth` في CSS يبتلع scrollBy البرمجي مع scroll-snap
        (قيست: صفر حركة). فالسلاسة تمرر في الاستدعاء لا في الورقة.
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

    /* هامش الطرف: `scroll-snap` يستقر عند |scrollLeft|=5 لا 0 (قيست) بسبب
       حشوة المسار، فعتبة 4px كانت تبقي «السابق» مفعلا في البداية. و12 تظل
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
    // بعد الفلترة يتغير عدد البطاقات فيتغير scrollWidth
    car.addEventListener('tq:filtered', sync);
    sync();
  });

  /* ---------- الكتالوج: فئة واحدة تفلتر المواد والكتب فعليا ----------
     كان الاختيار سابقا يبدل نص العنوان فقط ولا يمس أي بطاقة. الفلترة الآن
     على البيانات التي يولدها البناء من catalog.json (data-cat على كل بطاقة). */
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

    // نفس المنتقي يخدم صفحتي المسارات والكتب؛ العنوان يتبع الشبكة الموجودة فعلا
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

      /* النتيجة المفلترة تصير عنوانا يشارك ويحفظ وينجو من التحديث.
         القارئ يقرأ `?cat=` أصلا عند التحميل — فكان نصف الطريق مبنيا
         والنصف الآخر مفقودا. و`replaceState` لا `pushState`: الفلترة
         ليست صفحة جديدة، وزر الرجوع يجب أن يخرج من الصفحة لا أن
         يتراجع خطوة خطوة في المرشحات. */
      var u = new URL(location.href);
      if (card.dataset.cat) u.searchParams.set('cat', card.dataset.cat);
      else u.searchParams.delete('cat');
      history.replaceState(null, '', u);
    });
  }

  /* ---------- المعلمون: بحث وفلترة وفرز ---------- */
  /* TQ-DIR-AR · البحث كان `indexOf` على النص كما كتب: فمن كتب «احمد»
     لا يجد «أحمد»، ومن كتب «ساره» لا يجد «سارة»، ومن كتب «MATH» لا
     يجد «math». والعربي يكتب بهمزات وتاء مربوطة وألف مقصورة تختلف من
     كاتب إلى كاتب، فالمقارنة تسوى على الطرفين قبل أن تقع. */
  function tqNorm(v) {
    return String(v || '')
      .toLowerCase()
      .replace(/[ً-ْـ]/g, '')            /* تشكيل وتطويل */
      .replace(/[أإآٱ]/g, 'ا') /* أ إ آ ← ا */
      .replace(/ة/g, 'ه')                     /* ة ← ه */
      .replace(/ى/g, 'ي')                     /* ى ← ي */
      .replace(/\s+/g, ' ')
      .trim();
  }

  var teacherGrid = $('#teacherGrid');
  if (teacherGrid) {
    var cards = $$('.teacher-card', teacherGrid);
    var empty = $('#teacherEmpty');
    var search = $('#teacherSearch');
    var headerSearch = $('#headerTeacherSearch');
    var stageSel = $('#teacherStage');
    var sortSel = $('#teacherSort');
    var moreBtn = $('#teacherMore');
    var moreLbl = moreBtn && moreBtn.querySelector('[data-tq-morelbl]');
    var expanded = false;

    cards.forEach(function (c) { c.dataset.norm = tqNorm(c.dataset.search || c.dataset.name); });

    var apply = function () {
      var q = tqNorm((search && search.value) || '');
      var stage = (stageSel && stageSel.value) || '';
      var matched = 0, hiddenByFold = 0;

      /* الفرز أولا ثم الطي: كان الفرز يقع بعد قرار الإخفاء، فتكشف
         الطية العشر الأولى بالترتيب القديم لا بالترتيب المعروض. */
      if (sortSel) {
        var key = sortSel.value;
        var attr = (key === 'reviews') ? 'reviews' : (key === 'courses' ? 'courses' : 'rating');
        cards.slice()
          .sort(function (a, b) {
            return (parseFloat(b.dataset[attr]) || 0) - (parseFloat(a.dataset[attr]) || 0);
          })
          .forEach(function (c) { teacherGrid.appendChild(c); });
      }

      /* البحث أو الترشيح يلغي الطية: من بحث يريد كل ما طابق، لا عشرة
         منه وزرا يطلب البقية. */
      var filtering = (q !== '' || stage !== '');

      cards.forEach(function (c) {
        var ok = (!q || (c.dataset.norm || '').indexOf(q) !== -1) &&
                 (!stage || c.dataset.stage === stage);
        if (ok) matched++;

        var folded = c.dataset.fold === '1' && !expanded && !filtering;
        if (ok && folded) hiddenByFold++;

        c.hidden = !ok || folded;
      });

      if (empty) empty.hidden = matched !== 0;
      if (moreBtn) {
        /* الزر يختفي حين لا يخفي شيئا — زر يضغط ولا يتغير تحته شيء
           أسوأ من غياب الزر. */
        moreBtn.hidden = filtering || (hiddenByFold === 0 && !expanded);
        if (moreLbl) {
          moreLbl.textContent = expanded
            ? (moreBtn.dataset.labelLess || '')
            : (moreBtn.dataset.labelMore || '');
        }
        moreBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      }
    };

    [search, stageSel, sortSel].forEach(function (el) {
      if (!el) return;
      el.addEventListener('input', apply);
      /* `change` إلى جانب `input`: قوائم `select` في بعض المتصفحات لا
         تصدر `input`، فيبقى المرشح لا يعمل بلا خطأ يظهر. */
      el.addEventListener('change', apply);
    });

    if (moreBtn) {
      moreBtn.addEventListener('click', function () {
        expanded = !expanded;
        apply();
      });
    }

    // البحث في الهيدر يغذي حقل الدليل نفسه بدل أن يكون فلترا ثانيا مستقلا
    if (headerSearch && search) {
      headerSearch.addEventListener('input', function () {
        search.value = headerSearch.value;
        apply();
      });
    }

    apply();
  }

  /* ---------- المدونة: تصنيفات وبحث ---------- */
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

  /* ---------- النماذج: تحقق محلي ثم حالة نجاح ---------- */
  $$('form[data-validate]').forEach(function (form) {
    var ok = form.parentElement.querySelector('[data-ok]');

    form.addEventListener('submit', function (e) {
      /* TQ-GATE — المنع يقرر بعد التحقق لا قبله.
         كان هنا منع غير مشروط من مرحلة العرض التصميمي، وبقي بعد
         ربط النموذج بالخادم — فكل رسالة تكتب تمسح ويقال لصاحبها
         «تم استلام رسالتك». الترتيب الصحيح أدناه. */
      var bad = null;

      $$('input, textarea, select', form).forEach(function (f) {
        if (!f.name || f.disabled) return;
        /* حقل لا يراه صاحبه لا يمنع الإرسال: المخفي بـ`hidden` أو بحاوية
           مطوية يرفض بلا أن يظهر سبب الرفض — فيبقى الزر لا يستجيب. */
        if (f.type === 'hidden' || !f.offsetParent) return;
        var value = f.value.trim();
        var valid = f.required ? value !== '' : true;
        /* الفراغ في حقل اختياري ليس بريدا خاطئا: فحص الصيغة على قيمة
           موجودة وحدها، وإلا رد النموذج بحقل تركه صاحبه عمدا. */
        if (valid && f.type === 'email' && value) valid = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value);
        if (valid && f.type === 'tel' && value) valid = /^5[0-9]{8}$/.test(value);

        var field = f.closest('.form-field');
        if (field) field.classList.toggle('form-field--invalid', !valid);
        if (!valid && !bad) bad = f;
      });

      if (bad) { e.preventDefault(); bad.focus(); return; }

      /* نموذج له وجهة يمضي إليها؛ والتزييف لنموذج العرض وحده. */
      if (form.getAttribute('action')) { return; }
      e.preventDefault();
      form.reset();
      if (ok) {
        ok.classList.add('is-on');
        setTimeout(function () { ok.classList.remove('is-on'); }, 6000);
      }
    });

    // يختفي التحذير بمجرد أن يبدأ المستخدم في التصحيح
    form.addEventListener('input', function (e) {
      var field = e.target.closest('.form-field');
      if (field) field.classList.remove('form-field--invalid');
    });
  });
})();

/* ---- فيديو الهيرو ---------------------------------------------------
   يعمل على الجوال والحاسوب معا (طلب المالك). والثمن يخفض لا يدفع
   كاملا: نسخة بعرض 720 (471 ك.ب) دون 980px، وبعرض 1280 (1.3 م.ب) فوقها.
   ويحترم `prefers-reduced-motion` ووضع توفير البيانات — الأول تفضيل
   صحي لا ذوقي، والثاني إعلان صريح بأن البايت يحاسب عليه. */
(function () {
  var v = document.querySelector('[data-tq-hero-video]');
  if (!v) return;
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  if (navigator.connection && navigator.connection.saveData) return;

  var small = window.matchMedia('(max-width: 980px)').matches;
  var base = v.getAttribute('poster');
  /* WebM أولا وMP4 بديلا: سفاري القديم وبعض أجهزة iOS لا تفك WebM،
     فكانت الخلفية تبقى صورة ساكنة بلا سبب ظاهر. و`canPlayType` تسأل
     المتصفح بدل أن نخمن عنه. */
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

  /* خارج الشاشة لا يشغل: إطارات لا ترى تستهلك بطارية بلا مقابل،
     وهو على الجوال أهم منه على الحاسوب. */
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (es) {
      es.forEach(function (e) { e.isIntersecting ? play() : v.pause(); });
    }, { threshold: 0.05 }).observe(v);
  }
})();

/* ---- الكاروسل ------------------------------------------------------
   السحب والزخم وحد التمرير من المتصفح عبر `scroll-snap`. وهذا هنا
   للأزرار وتعطيلها عند الطرفين — وفي RTL يكون `scrollLeft` سالبا في
   المتصفحات الحديثة، فالحساب بالمقدار لا بالإشارة. */
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

    /* TQ-CAROUSEL-WHEEL — عجلة الفأرة الرأسية تحرك المسار أفقيا.
       لوحة اللمس ترسل `deltaX` فيمررها المتصفح وحده، أما الفأرة فلا
       ترسل إلا `deltaY` — فمن يمرر فوق الكاروسل بفأرة كان يمر بالصفحة
       كلها ولا تتحرك بطاقة، وهو الجهاز الأشيع على الحاسوب.

       وثلاثة قيود تمنع خطف تمرير الصفحة:
       ١ — الأفقي الصريح يترك للمتصفح (`deltaX` أكبر) فلا يعالج مرتين.
       ٢ — الميل الرأسي الغالب وحده يحول، وقطريه يترك للصفحة.
       ٣ — عند الطرف لا يمنع الافتراضي، فالصفحة تكمل تمريرها ولا يعلق
           القارئ في مسار انتهى. */
    track.addEventListener('wheel', function (e) {
      if (e.ctrlKey) return;                                  /* تكبير لا تمرير */
      if (Math.abs(e.deltaX) >= Math.abs(e.deltaY)) return;   /* أفقي: للمتصفح */

      var max = track.scrollWidth - track.clientWidth;
      if (max <= 2) return;                                   /* لا شيء يمرر */

      /* RTL: `scrollLeft` سالب في المتصفحات الحديثة، فالحساب بالمقدار
         والدفع بإشارة الاتجاه. */
      var sign = getComputedStyle(track).direction === 'rtl' ? -1 : 1;
      var at   = Math.abs(track.scrollLeft);
      var down = e.deltaY > 0;
      if ((down && at >= max - 2) || (!down && at <= 2)) return;

      e.preventDefault();
      track.scrollBy({ left: sign * e.deltaY, behavior: 'auto' });
    }, { passive: false });

    /* TQ-CAROUSEL-HIDDEN — الشرائح قد تخفى وتظهر تحت الكاروسل نفسه:
       تبويب المرحلة في صفحة الباقات يخفي ما ليس مرحلته، فيتغير
       `scrollWidth` بلا تمرير ولا تغيير حجم — والزران يبقيان على حالهما
       الأول: «التالي» معطل وثلاث بطاقات وراءه، أو مفعل ولا شيء بعده.
       والمضمار يعاد إلى أوله أيضا: من كان في آخر مرحلة ثم بدلها يجد
       نفسه في فراغ بعد آخر بطاقة. */
    if ('MutationObserver' in window) {
      new MutationObserver(function () {
        track.scrollTo({ left: 0, behavior: 'auto' });
        sync();
      }).observe(track, { attributes: true, subtree: true, attributeFilter: ['hidden'] });
    }

    sync();
  });
})();

/* ---- تبويب المرحلة --------------------------------------------------
   البطاقات كلها في الصفحة ويخفى ما لا يخص المرحلة — فالتبديل فوري
   بلا طلب. والخادم يخفي غير الافتراضية أصلا، فلو فشل هذا السكربت
   رأى الزائر مرحلة واحدة صحيحة لا ست بطاقات مختلطة. */
(function () {
  var tabs = document.querySelectorAll('[data-tq-stage]');
  /* TQ-DEADTAIL — كان هنا `if (!tabs.length) return;`، وهذه الدالة لا
     تحمل تبويب المرحلة وحده: بعدها في الغلاف نفسه مرساة الروابط، **وزر
     إظهار كلمة المرور**، وزر نسخ بيانات التحويل، ومبدل الباقة.
     و`data-tq-stage` غير موجود في أي قالب في المشروع، فالشرط يصدق في كل
     صفحة ويقطع الغلاف عند أول سطر — فزر العين في الدخول والتسجيل لم
     يعمل قط: ينقر فلا يظهر شيء ولا يظهر خطأ.
     والحذف مأمون: حلقة على `NodeList` فارغة لا تفعل شيئا، و`pick` بلا
     بطاقات لا تفعل شيئا. */
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
    /* الأسهم تتنقل بين التبويبات كما يتوقع قارئ الشاشة */
    t.addEventListener('keydown', function (e) {
      if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
      e.preventDefault();
      var arr = Array.prototype.slice.call(tabs);
      var i = arr.indexOf(t);
      var d = (e.key === 'ArrowLeft') ? 1 : -1;   /* RTL: اليسار يتقدم */
      var n = arr[(i + d + arr.length) % arr.length];
      n.focus(); n.click();
    });
  });

  /* ---------- المرساة: تفتح المرحلة ثم يمرر ----------
     ثلاث مشكلات في رابط واحد مثل `/plans#plus-middle`:
       · الهدف قد يكون `hidden` — تبويب المرحلة يخفي ما ليس مرحلته،
         فثلاث من روابط التسعير الست تشير إلى عناصر لا ترسم أصلا.
       · والمتصفح يمرر عند التحليل، ثم تغير الصور المتأخرة التخطيط
         فيبقى الموضع خاطئا — قست `scrollY=0` والهدف عند ٤٩٨px.
       · و`scroll-margin` وحدها لا تصلح ما لم يقع تمرير أصلا.
     فيؤخر التمرير إلى ما بعد استقرار التخطيط، وتفتح المرحلة قبله. */
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
    /* تصحيح واحد بعد استقرار الصور: القفزة الأولى تضع الزائر في المكان،
       وصورة كسولة تصل بعدها تزيحه — فيعاد الضبط مرة لا حلقة. */
    setTimeout(jump, 700);
  };
  window.addEventListener('load', tqAnchor);
  window.addEventListener('hashchange', tqAnchor);


  /* ---------- إظهار كلمة المرور ----------
     الحقل المخفي لا يقول لصاحبه أخطأ في حرف أم في لغة لوحة المفاتيح.
     والزر يبدل النوع ويعلن حالته لقارئ الشاشة بـ`aria-pressed`. */
  document.querySelectorAll('[data-tq-pw]').forEach(function (btn) {
    var inp = document.getElementById(btn.getAttribute('data-tq-pw'));
    if (!inp) return;
    btn.addEventListener('click', function () {
      var show = inp.type === 'password';
      inp.type = show ? 'text' : 'password';
      btn.setAttribute('aria-pressed', String(show));
      btn.setAttribute('aria-label', show ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور');
      var u = btn.querySelector('use');
      if (u) u.setAttribute('href', show ? '#i-eye-off' : '#i-eye');
      /* المؤشر يعاد إلى آخر الحرف: `type` يعاد ضبطه فيقفز إلى أوله في
         بعض المتصفحات، فيكتب من يواصل الكتابة في غير موضعه. */
      inp.focus();
      try { var n = inp.value.length; inp.setSelectionRange(n, n); } catch (err) {}
    });
  });

  /* ---------- نسخ بيانات التحويل ----------
     الآيبان أربعة وعشرون محرفا يملى بالعين من شاشة إلى تطبيق بنك:
     محرف واحد يخطئ فترتد الحوالة، ولا يعرف الطالب لماذا.

     و`navigator.clipboard` يشترط سياقا آمنا وقد يرفض بلا استثناء
     يلتقط — فله بديل قديم يعمل حيث لا يعمل. والفشل يقال صراحة:
     زر يبتلع الضغطة صامتا يجعل المستخدم يظن أنه نسخ. */
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
        btn.setAttribute('aria-label', ok ? 'نسخ' : 'تعذر النسخ — حدده وانسخه يدويا');
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

  /* ---------- مبدل الباقة: إغلاق بالنقر خارجها وبـEscape ----------
     `<details>` تفتح وتغلق بلا سكربت، وهذا هو الأساس الذي يعمل دائما.
     وما يضاف هنا سلوك قائمة لا سلوك تفصيل: قائمة تبقى مفتوحة بعد أن
     ينصرف عنها المستخدم تحجب ما تحتها. فإن لم يعمل هذا الملف بقي
     التبديل عاملا — بنقرة إغلاق زائدة لا أكثر. */
  var tqSwitches = document.querySelectorAll('[data-tq-switch]');
  if (tqSwitches.length) {
    document.addEventListener('click', function (e) {
      tqSwitches.forEach(function (d) {
        if (d.open && !d.contains(e.target)) d.open = false;
      });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      tqSwitches.forEach(function (d) {
        if (!d.open) return;
        d.open = false;
        var sum = d.querySelector('summary');
        if (sum) sum.focus();        /* التركيز يعود إلى ما فتحها */
      });
    });
  }

})();

/* ==== تحقق نماذج الحساب ==============================================
   الدخول والتسجيل والاستعادة نماذج تكتب فيها كلمات سر وأعمار وأرقام،
   وكانت بلا تحقق في المتصفح إطلاقا: `sign_up` يحمل `novalidate` ولا
   يحمل `data-validate`، فيبطل تحقق المتصفح ولا يحل محله شيء. والنتيجة
   أن كل خطأ — حرف ناقص في كلمة المرور، بريد بلا نقطة — يسافر إلى
   الخادم، فيعود ردا يمسح النموذج كله برسالة واحدة أعلى الصفحة.

   وهذا يتحقق **قبل** الإرسال ويقول لكل حقل ما به تحته مباشرة. وهو
   طبقة راحة لا طبقة أمان: الخادم يعيد الفحص كله في `Login::register`،
   فمن عطل السكربت لا يمر بشيء.

   والقواعد تقرأ من الوسم نفسه (`required` و`minlength` و`min`/`max`
   و`type`) كي لا يفترق الحقل عن قاعدته حين يعدل أحدهما دون الآخر،
   ويزاد عليها `data-match` للتأكيد و`data-msg` لرسالة خاصة.
   ==================================================================== */
(function () {
  'use strict';

  var forms = document.querySelectorAll('form[data-tq-auth]');
  if (!forms.length) return;

  var RE_MAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
  var AR_DIGITS = { '٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9' };
  var toLatin = function (d) { return AR_DIGITS[d]; };

  /* الأرقام العربية والمسافات والشرط تطبع قبل الفحص — كما يطبعها
     الخادم حرفا بحرف في `Login::register`. فما يقبله أحدهما يقبله
     الآخر، ولا يرد المتصفح رقما سيقبله الخادم. */
  function normPhone(v) {
    return String(v).replace(/[٠-٩]/g, toLatin)
      .replace(/[^0-9]/g, '').replace(/^(?:00966|966)/, '').replace(/^0/, '');
  }

  /* اسم الحقل كما يقرؤه صاحبه: `.sr-only` بجانبه هو تسميته الحقيقية،
     و`placeholder` بديل — فلا تكتب الرسالة «هذا الحقل مطلوب». */
  function labelOf(el) {
    var box = el.closest('.form-field');
    var s = box && box.querySelector('.sr-only');
    var t = (s && s.textContent) || el.getAttribute('placeholder') || 'هذا الحقل';
    return t.replace(/\s*\(.*\)\s*$/, '').trim();
  }

  function slotFor(el) {
    var host = el.closest('.form-cell');
    if (!host) {
      var box = el.closest('.form-field, .form-consent');
      host = box && box.parentNode;
    }
    if (!host) return null;
    var slot = host.querySelector('.field-err');
    if (!slot) {
      slot = document.createElement('p');
      slot.className = 'field-err';
      slot.setAttribute('role', 'alert');
      host.appendChild(slot);
    }
    return slot;
  }

  /* الخطأ يقال بثلاثة معا: لون الحد، ونص تحت الحقل، و`aria-invalid`
     لمن يسمع الصفحة ولا يراها. */
  function mark(el, msg) {
    var box = el.closest('.form-field');
    var slot = slotFor(el);
    if (box) box.classList.toggle('form-field--invalid', !!msg);
    if (slot) { slot.textContent = msg || ''; slot.hidden = !msg; }
    if (msg) el.setAttribute('aria-invalid', 'true');
    else el.removeAttribute('aria-invalid');
    return !msg;
  }

  /* حقل لا يظهر على الشاشة لا يفحص: حقول البوابة المطوية ترفض بلا أن
     يرى صاحبها سبب الرفض، فيبدو زر الإرسال معطلا بلا سبب.
     و`document` مقصوص بـ`.sr-only` عمدا فيقاس بحاويته لا بنفسه. */
  function live(el) {
    if (el.disabled || el.type === 'hidden') return false;
    var box = el.closest('.form-field, .form-consent');
    return !!((box && box.offsetParent) || el.offsetParent);
  }

  function check(el, form) {
    if (!live(el)) return mark(el, '');

    var custom = el.getAttribute('data-msg');
    var label = labelOf(el);

    if (el.type === 'checkbox') {
      return mark(el, (el.required && !el.checked) ? (custom || 'لا بد من تأكيد هذا الخيار.') : '');
    }

    if (el.type === 'file') {
      var has = el.files && el.files.length > 0;
      if (el.required && !has) return mark(el, custom || ('أرفق ' + label + '.'));
      if (!has) return mark(el, '');
      var ok = (el.getAttribute('accept') || '').split(',')
        .map(function (s) { return s.trim().toLowerCase(); }).filter(Boolean);
      var nm = el.files[0].name.toLowerCase();
      if (ok.length && !ok.some(function (x) { return nm.slice(-x.length) === x; })) {
        return mark(el, 'صيغة الملف غير مقبولة. المقبول: PDF · JPG · PNG.');
      }
      /* حد الرفع في `php.ini`، وتجاوزه يعود صفحة بيضاء لا رسالة. */
      if (el.files[0].size > 5 * 1024 * 1024) {
        return mark(el, 'حجم الملف أكبر من خمسة ميغابايت.');
      }
      return mark(el, '');
    }

    var v = String(el.value).trim();
    /* «اكتب كذا» لا «كذا مطلوب»: العربية تؤنث الصفة، فقالب واحد يخرج
       «كلمة المرور مطلوب» و«نبذة مطلوب». والفعل يستقيم مع كل اسم.
       و`data-msg` هنا رسالة الصيغة لا رسالة الفراغ — الفارغ لم يخطئ
       في الصيغة، إنما لم يكتب بعد. */
    if (el.required && v === '') {
      return mark(el, (el.tagName === 'SELECT' ? 'اختر ' : 'اكتب ') + label + '.');
    }
    if (v === '') return mark(el, '');

    if (el.type === 'email' && !RE_MAIL.test(v)) {
      return mark(el, 'اكتب بريدا إلكترونيا صحيحا، مثل name@example.com');
    }
    if (el.type === 'tel' && !/^5[0-9]{8}$/.test(normPhone(v))) {
      return mark(el, 'رقم جوال سعودي من عشر خانات، مثل 0512345678');
    }

    var minLen = parseInt(el.getAttribute('minlength'), 10);
    if (!isNaN(minLen) && v.length < minLen) {
      return mark(el, custom || ('الحد الأدنى ' + minLen + ' محارف.'));
    }
    var maxLen = parseInt(el.getAttribute('maxlength'), 10);
    if (!isNaN(maxLen) && v.length > maxLen) {
      return mark(el, 'الحد الأعلى ' + maxLen + ' محرفا.');
    }

    if (el.type === 'number') {
      if (!/^[0-9٠-٩]+$/.test(v)) return mark(el, 'اكتب رقما.');
      var n = Number(v.replace(/[٠-٩]/g, toLatin));
      var lo = parseFloat(el.getAttribute('min')), hi = parseFloat(el.getAttribute('max'));
      if ((!isNaN(lo) && n < lo) || (!isNaN(hi) && n > hi)) {
        return mark(el, custom || ('اكتب رقما بين ' + lo + ' و' + hi + '.'));
      }
    }

    var twin = el.getAttribute('data-match');
    if (twin) {
      var other = form.querySelector('#' + twin);
      if (other && other.value !== el.value) {
        return mark(el, custom || 'القيمتان غير متطابقتين.');
      }
    }
    return mark(el, '');
  }

  Array.prototype.forEach.call(forms, function (form) {
    function fields() {
      return Array.prototype.slice.call(form.querySelectorAll('input, select, textarea'))
        .filter(function (f) { return f.name && f.type !== 'hidden'; });
    }

    form.addEventListener('submit', function (e) {
      var bad = null;
      fields().forEach(function (f) { if (!check(f, form) && !bad) bad = f; });

      if (bad) {
        e.preventDefault();
        bad.focus({ preventScroll: true });
        (bad.closest('.form-cell') || bad.closest('.form-field') || bad)
          .scrollIntoView({ block: 'center', behavior: 'smooth' });
        return;
      }

      /* نقرتان على «إنشاء الحساب» تعنيان طلبين — والثاني يرد «لديك
         حساب بالفعل». فيقفل الزر بعد أن يمضي الإرسال: تعطيله في اللحظة
         نفسها يسقطه من الـPOST في بعض المتصفحات. */
      var send = form.querySelector('[type=submit]');
      if (send) {
        setTimeout(function () { send.disabled = true; send.classList.add('is-busy'); }, 0);
      }
    });

    /* الرسالة تختفي حالما يبدأ التصحيح، وتعاد عند مغادرة الحقل. */
    form.addEventListener('input', function (e) {
      var t = e.target;
      if (!t.name) return;
      mark(t, '');
      if (t.id) {
        var dep = form.querySelector('[data-match="' + t.id + '"]');
        if (dep && dep.value) check(dep, form);
      }
    });
    form.addEventListener('change', function (e) {
      if (e.target.name && (e.target.type === 'file' || e.target.type === 'checkbox')) {
        check(e.target, form);
      }
    });
    form.addEventListener('blur', function (e) {
      if (e.target.name && e.target.value !== '') check(e.target, form);
    }, true);
  });
})();

/* ---- الكتالوج: بحث حي ومرشحات وترقيم ---------------------------------
   ═══ لماذا لا يرشح في المتصفح ═══

   الترشيح في الجافاسكربت يعني نسخة ثانية من قواعد الترشيح: واحدة في
   `Taqdar_catalog_model` وأخرى هنا. وهما تفترقان عند أول تعديل — يضاف
   نوع خامس في الخادم فيراه من يفتح الرابط ولا يراه من يكتب في صندوق
   البحث. وأسوأ منه: عدادات المرشحات لا تحسب في المتصفح إلا على ما نزل
   منها فعلا، وقد نزلت اثنتا عشرة بطاقة من إحدى وثلاثين.

   فالخادم يرشح، وهذه الكتلة تجلب الجزء وتستبدله. والحال في **الرابط
   وحده**: لا كائن حال هنا يمكن أن يفترق عما يفهمه الخادم. وكل نقرة
   تكتب الرابط ثم تجلب منه — والرجوع بزر المتصفح يمر بالطريق نفسه.

   ═══ ويعمل بلا هذا الملف ═══

   كل خيار رابط `<a>` وكل بحث نموذج `GET`. فمن عطل الجافاسكربت أو تعثر
   تحميله رأى الصفحة كاملة تعاد تحميلا — بالنتيجة نفسها. */
(function () {
  var grid = document.querySelector('[data-tq-cat-grid]');
  if (!grid) return;

  var endpoint = grid.getAttribute('data-tq-cat-grid');
  if (!endpoint) return;

  var form    = document.querySelector('[data-tq-cat-form]');
  var input   = document.querySelector('[data-tq-cat-q]');
  var sorter  = document.querySelector('[data-tq-cat-sort]');
  var rail    = document.querySelector('[data-tq-cat-rail-box]');
  var railBtn = document.querySelector('[data-tq-cat-rail]');
  var counter = document.getElementById('catCount');
  var goBtn   = document.querySelector('[data-tq-cat-go]');
  var clrBtn  = document.querySelector('[data-tq-cat-clear]');

  /* زر «ابحث» لمن لا سكربت عنده: هنا يبحث وقت الكتابة فلا معنى له.
     ويخفى من هنا لا من القالب — القالب لا يعرف أوصل هذا الملف أم لا. */
  if (goBtn) goBtn.hidden = true;

  var timer = null;
  var ctrl  = null;
  var seq   = 0;

  function toggleClear() {
    if (clrBtn && input) clrBtn.hidden = (input.value === '');
  }

  /** يستبدل الأجزاء الثلاثة معا — والثلاثة من رد واحد فلا تتفارق. */
  function paint(data) {
    grid.innerHTML = data.grid;
    if (rail && typeof data.filters === 'string') rail.innerHTML = data.filters;
    if (counter && typeof data.count === 'string') counter.innerHTML = data.count;

    /* البطاقات الجديدة تظهر فورا: مراقب الظهور في أعلى الملف يعمل مرة
       واحدة عند التحميل، فما حقن بعده يبقى شفافا إلى الأبد. */
    var fresh = grid.querySelectorAll('.reveal');
    for (var i = 0; i < fresh.length; i++) fresh[i].classList.add('is-in');
    if (rail) {
      var rf = rail.querySelectorAll('.reveal');
      for (var j = 0; j < rf.length; j++) rf[j].classList.add('is-in');
    }
  }

  /**
   * يجلب النتيجة لرابط كتالوج ويستبدل الأجزاء.
   *
   * `mode`: push (نقرة تسجل في التاريخ) · replace (كتابة في صندوق البحث
   * — تسجيل كل حرف يجعل زر الرجوع يمر بالكلمة حرفا حرفا) · none (رجوع).
   */
  function load(url, mode) {
    var qs = url.indexOf('?') >= 0 ? url.slice(url.indexOf('?') + 1) : '';
    var my = ++seq;

    if (ctrl && typeof ctrl.abort === 'function') ctrl.abort();
    ctrl = (typeof AbortController === 'function') ? new AbortController() : null;

    grid.setAttribute('aria-busy', 'true');
    grid.classList.add('is-loading');

    fetch(endpoint + (qs ? '?' + qs : ''), {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      signal: ctrl ? ctrl.signal : undefined
    })
      .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
      .then(function (data) {
        /* رد متأخر لطلب سابق لا يكتب فوق رد أحدث: من يكتب بسرعة يصدر
           طلبين، والشبكة لا تضمن ترتيب وصولهما. */
        if (my !== seq) return;
        paint(data);
        var next = data.url || url;
        if (mode === 'push')         history.pushState({ tq: 1 }, '', next);
        else if (mode === 'replace') history.replaceState({ tq: 1 }, '', next);
      })
      .catch(function (e) {
        if (e && e.name === 'AbortError') return;
        /* الشبكة تعثرت: ينتقل انتقالا كاملا بدل أن يبقى الزائر أمام
           نتيجة قديمة تحتها مرشح يقول غيرها. */
        if (my === seq) window.location.href = url;
      })
      .then(function () {
        if (my !== seq) return;
        grid.removeAttribute('aria-busy');
        grid.classList.remove('is-loading');
      });
  }

  /** الرابط الحالي ومعه تعديل — نفس عقد `tqs_cat_query` في الخادم. */
  function urlWith(set) {
    var u = new URL(window.location.href);
    u.pathname = new URL(endpoint, window.location.origin).pathname.replace(/\/results$/, '');
    var keys = Object.keys(set);
    /* أي تعديل غير رقم الصفحة يعيدها إلى الأولى: الصفحة السابعة من
       نتيجة قديمة لا وجود لها في نتيجة جديدة. */
    for (var i = 0; i < keys.length; i++) if (keys[i] !== 'page') { u.searchParams.delete('page'); break; }
    for (var k in set) {
      if (!Object.prototype.hasOwnProperty.call(set, k)) continue;
      if (set[k] === null || set[k] === '') u.searchParams.delete(k);
      else u.searchParams.set(k, set[k]);
    }
    return u.toString();
  }

  /* ---- النقر على أي خيار أو رقم صفحة ----
     التفويض على المستند لا على العناصر: اللوحة والشبكة تستبدلان مع كل
     تحديث، ومستمع على عنصر مستبدل يموت معه. */
  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('[data-tq-cat-link]') : null;
    if (!a) return;
    /* الفتح في تبويب جديد يبقى فتحا في تبويب جديد */
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
    e.preventDefault();
    load(a.href, 'push');

    /* رقم صفحة: يعاد الزائر إلى أعلى النتائج — وإلا بقي حيث كان فرأى
       ذيل الصفحة الجديدة وظن أنه لم يتغير شيء. */
    if (a.hasAttribute('data-tq-page')) {
      var top = document.getElementById('catalog');
      if (top) top.scrollIntoView({ block: 'start', behavior: 'smooth' });
    }
  });

  /* ---- الكتابة في صندوق البحث ----
     التأخير ٢٦٠ مللي: أقل منه يصدر طلبا لكل حرف، وأكثر منه يحس تأخرا. */
  if (input) {
    input.addEventListener('input', function () {
      toggleClear();
      clearTimeout(timer);
      timer = setTimeout(function () {
        load(urlWith({ q: input.value.trim() }), 'replace');
      }, 260);
    });
    /* الإدخال يبحث فورا بلا انتظار المؤقت */
    input.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      clearTimeout(timer);
      load(urlWith({ q: input.value.trim() }), 'push');
    });
  }

  if (clrBtn && input) {
    clrBtn.addEventListener('click', function () {
      input.value = '';
      toggleClear();
      input.focus();
      clearTimeout(timer);
      load(urlWith({ q: null }), 'push');
    });
  }

  if (sorter) {
    sorter.addEventListener('change', function () {
      load(urlWith({ sort: sorter.value === 'featured' ? null : sorter.value }), 'push');
    });
  }

  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      clearTimeout(timer);
      load(urlWith({ q: input ? input.value.trim() : '' }), 'push');
    });
  }

  /* ---- لوحة المرشحات على الجوال ---- */
  if (railBtn && rail) {
    railBtn.addEventListener('click', function () {
      var open = railBtn.getAttribute('aria-expanded') !== 'true';
      railBtn.setAttribute('aria-expanded', String(open));
      rail.classList.toggle('is-open', open);
    });
  }

  /* ---- زر الرجوع ----
     الحال في الرابط، فالرجوع يعني إعادة الجلب منه — لا استرجاع وسم
     محفوظ في `state`: الوسم يشيخ إن تغير المحتوى بين الزيارتين. */
  window.addEventListener('popstate', function () {
    load(window.location.href, 'none');
  });

  toggleClear();
})();


/* ══════════════════════════════════════════════════════════════════
   TQ-P26-CYCLE · مبدّل عرض السعر في قسم الباقات
   يبدّل **ما يُعرض** لا ما يُدفع: كل الباقات سنوية، والشهريّ معادلها
   ومعه سطر «يُدفع سنويًّا». ولذلك لا يمسّ الزرّ ولا الرابط ولا الخادم.
   ولو لم يعمل هذا السكربت بقي السعر السنويّ ظاهرًا — وهو الصحيح.
   ══════════════════════════════════════════════════════════════════ */
(function () {
  var btns = document.querySelectorAll('[data-tq-cycle]');
  if (!btns.length) return;
  function apply(cycle) {
    Array.prototype.forEach.call(btns, function (b) {
      b.setAttribute('aria-pressed', String(b.getAttribute('data-tq-cycle') === cycle));
    });
    /* الكساءان معًا: `.p26-card__price` في صفحة الباقات البيضاء،
       و`.p26d-card__price` في الكتلة الداكنة بالرئيسية. */
    var prices = document.querySelectorAll(
      '.p26-card__price[data-cycle],.p26d-card__price[data-cycle]');
    Array.prototype.forEach.call(prices, function (p) {
      p.hidden = (p.getAttribute('data-cycle') !== cycle);
    });
  }
  Array.prototype.forEach.call(btns, function (b) {
    b.addEventListener('click', function () { apply(b.getAttribute('data-tq-cycle')); });
  });
})();
