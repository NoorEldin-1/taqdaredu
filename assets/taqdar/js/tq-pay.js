/**
 * TQ-EXPRESS-PAY — أزرار الدفع المباشر: Apple Pay · Google Pay · البطاقة.
 *
 * كل زر ينتهي إلى شيء واحد: رمز لمرة واحدة يوضع في نموذج الشراء القائم
 * (`tap_via` · `tap_token` · `tap_gpay`) ثم يرسل النموذج كما يرسله زره.
 * فلا نقطة دفع ثانية في الخادم: المتحكم الذي يصدر الفاتورة هو الذي يدفعها
 * بالرمز (`Taqdar_tap_model::start()`)، والعودة والتسوية من الباب نفسه.
 *
 * والمكتبات تجلب عند الحاجة وحدها: صفحة لم تفعل فيها Apple Pay لا تحمل
 * سكربته، وجهاز لا يدعمه لا يرى زره أصلا — زر لا يعمل أسوأ من غيابه.
 */
(function () {
  'use strict';

  var SDK = {
    apple:    'https://tap-sdks.b-cdn.net/apple-pay/build-1.2.0/main.js',
    appleCss: 'https://tap-sdks.b-cdn.net/apple-pay/build-1.2.0/main.css',
    card:     'https://tap-sdks.b-cdn.net/card/1.0.2/index.js',
    gpay:     'https://pay.google.com/gp/p/js/pay.js',
    // مكتبة أبل الرسمية: تعرف Apple Pay في كروم وإيدج وغيرهما، فيمسح
    // المشتري رمز QR بآيفونه ويدفع — وهو ما تعرضه صفحة تاب نفسها
    appleJs:  'https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js'
  };

  var loaded = {};
  function load(src) {
    if (loaded[src]) return loaded[src];
    loaded[src] = new Promise(function (ok, no) {
      var s = document.createElement('script');
      s.src = src; s.async = true;
      s.onload = function () { ok(); };
      s.onerror = function () { delete loaded[src]; no(new Error('load ' + src)); };
      document.head.appendChild(s);
    });
    return loaded[src];
  }
  function css(href) {
    if (document.querySelector('link[href="' + href + '"]')) return;
    var l = document.createElement('link');
    l.rel = 'stylesheet'; l.href = href;
    document.head.appendChild(l);
  }

  function Box(el) {
    this.el   = el;
    this.cfg  = JSON.parse(el.getAttribute('data-tqx-cfg') || '{}');
    this.form = document.getElementById(el.getAttribute('data-tqx-form'));
    this.stat = el.querySelector('[data-tqx-status]');
    this.busy = false;
    // اللغة من وسم الصفحة (رمز أيزو) لا من الإعداد: `tq_lang()` ترد اسما
    // («arabic») لا رمزا، والمقارنة به فتحت خانات البطاقة إنجليزية من اليسار
    this.ar   = /^ar/i.test(document.documentElement.lang || 'ar');
  }

  Box.prototype.has = function (m) { return (this.cfg.methods || []).indexOf(m) !== -1; };
  Box.prototype.major = function () {
    // المبلغ بالعملة الكبرى بخانتين — هو ما يعرض في نافذة الجهاز ويخصم
    return (this.cfg.amount / 100).toFixed(2);
  };

  Box.prototype.say = function (text, bad) {
    if (!this.stat) return;
    this.stat.hidden = !text;
    this.stat.textContent = text || '';
    this.stat.classList.toggle('is-bad', !!bad);
  };

  /**
   * يضع الرمز في نموذج الشراء ويرسله.
   * `pay_method` يثبت على `tap`: المتحكم لا يبدأ دفعا ببطاقة إلا به، ومنتقي
   * «تحويل بنكي» المختار سابقا لا يجوز أن يبتلع دفعة وثقها صاحبها ببصمته.
   */
  Box.prototype.submit = function (via, fields) {
    var f = this.form;
    if (!f || this.busy) return;
    this.busy = true;
    this.el.classList.add('is-busy');
    this.say(this.cfg.msg.paying, false);

    var radios = f.querySelectorAll('input[name="pay_method"]');
    var set = false;
    for (var i = 0; i < radios.length; i++) {
      if (radios[i].type === 'radio') {
        radios[i].checked = (radios[i].value === 'tap');
        if (radios[i].checked) set = true;
      } else { radios[i].value = 'tap'; set = true; }
    }
    // نماذج تسمي الوسيلة `method` (شراء ولي الأمر)
    var m = f.querySelectorAll('input[name="method"]');
    for (var j = 0; j < m.length; j++) {
      if (m[j].type === 'radio') m[j].checked = (m[j].value === 'card'); else m[j].value = 'card';
    }
    if (!set && !m.length) this.put('pay_method', 'tap');

    this.put('tap_via', via);
    for (var k in fields) if (Object.prototype.hasOwnProperty.call(fields, k)) this.put(k, fields[k]);

    // `submit()` لا `requestSubmit()`: لا يعاد فحص حقول لم تعد تعني شيئا،
    // ولا يطلق مستمع «submit» في الصفحة يعيد تسمية الزر أو يوقف الإرسال
    HTMLFormElement.prototype.submit.call(f);
  };

  Box.prototype.put = function (name, value) {
    var f = this.form;
    var el = f.querySelector('input[type="hidden"][name="' + name + '"]');
    if (!el) {
      el = document.createElement('input');
      el.type = 'hidden'; el.name = name;
      f.appendChild(el);
    }
    el.value = value;
  };

  Box.prototype.fail = function (text) {
    this.busy = false;
    this.el.classList.remove('is-busy');
    this.say(text || this.cfg.msg.failed, true);
  };

  /* ── Apple Pay ─────────────────────────────────────────────────────
     سفاري على جهاز أبل يعرف `ApplePaySession` أصلا. وما سواه (كروم
     وإيدج على ويندوز وأندرويد) تعرفه مكتبة أبل الرسمية: الزر يظهر، وعند
     الضغط يعرض رمز QR يمسحه المشتري بآيفونه فيوثق الدفع عليه.
     والشرط صفحة HTTPS: على `http://` ترمي `canMakePayments()` «insecure
     document» فيبقى الزر مخفيا — ولهذا لا يظهر على البيئة المحلية. */
  Box.prototype.apple = function () {
    var self = this, host = this.el.querySelector('[data-tqx-apple]');
    if (!host) return;

    var ready = window.ApplePaySession ? Promise.resolve() : load(SDK.appleJs);
    ready.then(function () {
      try {
        if (!window.ApplePaySession || !window.ApplePaySession.canMakePayments()) throw 0;
      } catch (e) { throw new Error('apple pay unavailable'); }
      css(SDK.appleCss);
      return load(SDK.apple);
    }).then(function () {
      var sdk = window.TapApplepaySDK;
      if (!sdk || !sdk.render) throw new Error('no sdk');
      var c = self.cfg.customer;
      host.hidden = false;
      sdk.render({
        debug: false,
        scope: 'TapToken',
        publicKey: self.cfg.public,
        environment: 'production',
        merchant: { domain: self.cfg.domain, id: self.cfg.merchant },
        acceptance: { supportedBrands: ['mada', 'masterCard', 'visa'] },
        features: { supportsCouponCode: false },
        transaction: { currency: self.cfg.currency, amount: self.major() },
        customer: {
          name: [{ locale: 'en', first: c.first, last: c.last }],
          contact: { email: c.email }
        },
        interface: {
          locale: self.ar ? 'ar' : 'en', theme: 'dark', type: 'buy', edges: 'curved'
        },
        onCancel: function () { self.say('', false); },
        onError: function (err) {
          if (window.console) console.warn('TQ Apple Pay', err);
          self.fail();
        },
        onSuccess: function (data) {
          var id = data && (data.id || (data.token && data.token.id));
          if (!id) return self.fail();
          self.submit('applepay', { tap_token: id });
        }
      }, host.id);
    }).catch(function () { host.hidden = true; });
  };

  /* ── Google Pay ────────────────────────────────────────────────────
     زر جوجل نفسه، وتاب «البوابة» التي تشفر لها الرسالة
     (`gateway: tappayments`). والرسالة المشفرة ترسل كما هي، ويصرفها
     الخادم رمزا عند تاب — لا تفك هنا ولا هناك. */
  Box.prototype.gpay = function () {
    var self = this, host = this.el.querySelector('[data-tqx-gpay]');
    if (!host) return;

    var method = {
      type: 'CARD',
      parameters: {
        allowedAuthMethods: ['PAN_ONLY', 'CRYPTOGRAM_3DS'],
        allowedCardNetworks: ['VISA', 'MASTERCARD']
      },
      tokenizationSpecification: {
        type: 'PAYMENT_GATEWAY',
        parameters: { gateway: 'tappayments', gatewayMerchantId: String(this.cfg.merchant) }
      }
    };
    var base = { apiVersion: 2, apiVersionMinor: 0 };

    load(SDK.gpay).then(function () {
      var client = new google.payments.api.PaymentsClient({
        environment: self.cfg.mode === 'live' ? 'PRODUCTION' : 'TEST'
      });
      return client.isReadyToPay(Object.assign({}, base, { allowedPaymentMethods: [method] }))
        .then(function (r) {
          if (!r || !r.result) return;
          var btn = client.createButton({
            buttonType: 'pay',
            buttonColor: 'black',
            buttonSizeMode: 'fill',
            buttonLocale: self.ar ? 'ar' : 'en',
            onClick: function () {
              if (self.busy) return;
              var req = Object.assign({}, base, {
                allowedPaymentMethods: [method],
                merchantInfo: { merchantName: self.cfg.gpayName },
                transactionInfo: {
                  totalPriceStatus: 'FINAL',
                  totalPriceLabel: self.cfg.label || 'Total',
                  totalPrice: self.major(),
                  currencyCode: self.cfg.currency,
                  countryCode: self.cfg.country
                }
              });
              if (self.cfg.gpayMerchant) req.merchantInfo.merchantId = self.cfg.gpayMerchant;
              client.loadPaymentData(req).then(function (pd) {
                var tok = pd && pd.paymentMethodData && pd.paymentMethodData.tokenizationData
                        && pd.paymentMethodData.tokenizationData.token;
                if (!tok) return self.fail();
                self.submit('googlepay', { tap_gpay: tok });
              }).catch(function (err) {
                // إغلاق النافذة ليس خطأ يقال
                if (err && err.statusCode === 'CANCELED') return;
                if (window.console) console.warn('TQ Google Pay', err);
                self.fail();
              });
            }
          });
          host.appendChild(btn);
          host.hidden = false;
        });
    }).catch(function () { host.hidden = true; });
  };

  /* ── البطاقة المباشرة ─────────────────────────────────────────────
     خانات تاب داخل صفحتنا في إطار منها: رقم البطاقة لا يمر بخادمنا ولا
     بسكربتنا. والزر يطلب الرمز، والرمز يرسل مع النموذج، والتحقق الثلاثي
     (رمز البنك) يقع بعد الإرسال في صفحة البنك كما يقع اليوم. */
  Box.prototype.card = function () {
    var self = this;
    var host = this.el.querySelector('[data-tqx-card]');
    var pay  = this.el.querySelector('[data-tqx-card-pay]');
    var box  = this.el.querySelector('[data-tqx-card-box]');
    if (!host || !pay) return;

    load(SDK.card).then(function () {
      var S = window.CardSDK;
      if (!S || !S.renderTapCard) throw new Error('no sdk');
      var L = S.Locale || {}, T = S.Theme || {}, E = S.Edges || {}, D = S.Direction || {};
      var C = S.Currencies || {};
      var dark = document.documentElement.getAttribute('data-theme') === 'dark';
      var c = self.cfg.customer;
      var ready = false;

      S.renderTapCard(host.id, {
        publicKey: self.cfg.public,
        merchant: { id: String(self.cfg.merchant || '') },
        transaction: { amount: self.cfg.amount / 100, currency: C[self.cfg.currency] || self.cfg.currency },
        customer: {
          name: [{ lang: L.EN || 'en', first: c.first, last: c.last }],
          nameOnCard: (c.first + ' ' + c.last).trim(),
          editable: true,
          contact: { email: c.email }
        },
        acceptance: { supportedBrands: ['VISA', 'MASTERCARD', 'MADA'], supportedCards: 'ALL' },
        fields: { cardHolder: true },
        addons: { displayPaymentBrands: true, loader: true, saveCard: false },
        interface: {
          locale: self.ar ? (L.AR || 'ar') : (L.EN || 'en'),
          theme: dark ? (T.DARK || 'dark') : (T.LIGHT || 'light'),
          edges: E.CURVED || 'curved',
          direction: self.ar ? (D.RTL || 'rtl') : (D.LTR || 'ltr')
        },
        onReady: function () { ready = true; pay.disabled = false; },
        onValidInput: function () { if (ready) pay.disabled = false; },
        onError: function (err) {
          if (window.console) console.warn('TQ Card', err);
          // خطأ قبل الجهوزية خطأ تركيب (مفتاح لا تعرفه تاب، أو نطاق غير
          // مسجل له): الخانات لن تعمل، فيرفع اللوح كله ويقال ذلك — لا زر
          // «ادفع» مفتوح فوق إطار ميت. وبعد الجهوزية خطأ بيانات بطاقة.
          if (!ready) {
            if (box) box.hidden = true;
            self.say(self.cfg.msg.load, true);
            return;
          }
          self.fail(self.cfg.msg.cardFail);
          pay.disabled = false;
        },
        onSuccess: function (data) {
          var id = data && data.id;
          if (!id) return self.fail(self.cfg.msg.cardFail);
          self.submit('card', { tap_token: id });
        }
      });

      pay.addEventListener('click', function () {
        if (self.busy) return;
        self.say('', false);
        try { S.tokenize(); } catch (e) { self.fail(self.cfg.msg.cardFail); }
      });
    }).catch(function () {
      // مكتبة لم تحمل (حجب شبكة مدرسة مثلا): يرفع اللوح كله ويقال ذلك،
      // وصفحة تاب ما زالت تحته في النموذج نفسه
      if (box) box.hidden = true;
      self.say(self.cfg.msg.load, true);
    });
  };

  /**
   * TQ-COUPON — الكود يغير الصافي بلا إعادة تحميل، فيتبعه كل زر.
   *
   * نافذة Apple Pay تعرض المبلغ الذي رسمت به وتوقع عليه؛ ورمز وقع على
   * ٣٩٩ يدفع به فاتورة صافيها ٣١٩ يرده البنك أو يعرض على المشتري رقما غير
   * الذي يخصم. فيعاد رسم زر Apple Pay بالصافي، وGoogle Pay يقرأ المبلغ
   * عند النقر أصلا، ونص زر البطاقة يتبع. وصاف صفر (هدية كاملة) لا يدفع
   * بشيء: يرفع اللوح ويبقى زر الشراء وحده، والمحرك يفعل بلا دفع.
   */
  Box.prototype.reprice = function (net) {
    net = parseInt(net, 10);
    if (!(net >= 0) || net === this.cfg.amount) return;
    this.cfg.amount = net;
    this.el.hidden = (net === 0);

    var pay = this.el.querySelector('[data-tqx-card-pay]');
    if (pay && this.cfg.msg.cardBtn) {
      var v = net / 100;
      pay.textContent = this.cfg.msg.cardBtn.replace('____',
        (net % 100 ? v.toFixed(2) : String(v)).replace(/\B(?=(\d{3})+(?!\d))/g, ','));
    }

    var host = this.el.querySelector('[data-tqx-apple]');
    if (host && !host.hidden && net > 0) {
      while (host.firstChild) host.removeChild(host.firstChild);
      this.apple();
    }
  };

  function init() {
    var els = document.querySelectorAll('[data-tqx]');
    for (var i = 0; i < els.length; i++) {
      if (els[i].__tqx) continue;
      var b = new Box(els[i]);
      els[i].__tqx = b;
      if (!b.form) continue;
      if (b.has('applepay'))  b.apple();
      if (b.has('googlepay')) b.gpay();
      if (b.has('card'))      b.card();
    }
    document.addEventListener('tq:coupon', function (e) {
      var d = (e && e.detail) || {};
      var net = d.applied ? d.net : d.gross;
      if (net === undefined || net === null) return;
      for (var j = 0; j < els.length; j++) if (els[j].__tqx) els[j].__tqx.reprice(net);
    });

    // من عاد بزر «رجوع» من صفحة البنك يجد الصفحة من ذاكرة المتصفح
    // وقد جمدت على «جار الدفع» — فتفك حين تعرض ثانية
    window.addEventListener('pageshow', function (e) {
      if (!e.persisted) return;
      for (var j = 0; j < els.length; j++) {
        var x = els[j].__tqx;
        if (x) { x.busy = false; x.el.classList.remove('is-busy'); x.say('', false); }
      }
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
