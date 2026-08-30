/**
 * لوحة تقدر — طبقة السلوك المشتركة.
 *
 * تحل محل بقيتين من قالب Hyper كانتا تظهران في كل شاشة من التسعين:
 *
 * ١ — **الرسالة الطائرة.** `$.NotificationApp.send()` يرسم صندوقا بألوان
 *     القالب القديم وحوافه، وكان يعرض **مع** `tqa-flash` المرسومة في
 *     `backend/index.php` — فرسالة الحفظ الواحدة تظهر مرتين بشكلين.
 *     هنا محرك واحد: `TQA.toast()`، و`NotificationApp` يعاد تعريفه فوقه
 *     فتخرج الشاشة الموروثة بالمظهر الجديد بلا أن تلمس.
 *
 * ٢ — **نافذة التأكيد.** كانت ثلاثا لا واحدة: `confirm()` الأصلية في
 *     شاشات تقدر (صندوق المتصفح، لا هوية له ولا عربية)، و`confirm_modal()`
 *     بأيقونة `dripicons` ونص إنجليزي في ٣٨ موضعا موروثا، و
 *     `ajax_confirm_modal()` نسخة ثالثة منها. وكلها تسأل السؤال نفسه.
 *     هنا `TQA.confirm()` واحدة ترجع وعدا، والاسمان القديمان يوجهان إليها.
 *
 * ولا تعتمد على Bootstrap ولا على jQuery في جوهرها: النافذة عنصر
 * `<dialog>` يبنى عند أول نداء. jQuery يستعمل — إن وجد — لجسر الأسماء
 * القديمة وحدها.
 */
(function (window, document) {
    'use strict';

    var TQA = window.TQA || {};
    window.TQA = TQA;

    /* =====================================================================
       ١ · الرسائل الطائرة
       ===================================================================== */

    var toastHost = null;

    function host() {
        if (toastHost && document.body.contains(toastHost)) return toastHost;
        toastHost = document.createElement('div');
        toastHost.className = 'tqa-toasts';
        /* `aria-live=polite` لا `assertive`: الرسالة تلي فعلا قام به
           المستخدم، فمقاطعة قارئ الشاشة في منتصف جملة لا تفيده. */
        toastHost.setAttribute('aria-live', 'polite');
        toastHost.setAttribute('aria-atomic', 'false');
        document.body.appendChild(toastHost);
        return toastHost;
    }

    var TONE_ICON = {
        ok:   '<path d="m5 12 4.5 4.5L19 7"/>',
        err:  '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.2v.01"/>',
        warn: '<path d="M12 4 2.8 20h18.4z"/><path d="M12 10v4M12 17.2v.01"/>',
        info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5.5M12 7.8v.01"/>'
    };

    function icon(tone, size) {
        return '<svg viewBox="0 0 24 24" width="' + (size || 18) + '" height="' + (size || 18) +
               '" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"' +
               ' stroke-linejoin="round" aria-hidden="true">' +
               (TONE_ICON[tone] || TONE_ICON.info) + '</svg>';
    }

    function normTone(t) {
        t = String(t || 'info').toLowerCase();
        if (t === 'success') return 'ok';
        if (t === 'error' || t === 'danger') return 'err';
        if (t === 'warning') return 'warn';
        return (t === 'ok' || t === 'err' || t === 'warn') ? t : 'info';
    }

    /**
     * يعرض رسالة طائرة.
     *
     * @param {string} message النص. يعامل نصا لا HTML — الرسائل تأتي من
     *                 الخادم أحيانا محملة بما كتبه مستخدم.
     * @param {string} tone    ok · err · warn · info
     * @param {object} opts    { timeout:ms, title:string }
     */
    TQA.toast = function (message, tone, opts) {
        message = String(message == null ? '' : message).trim();
        if (!message) return null;
        opts = opts || {};
        tone = normTone(tone);

        var el = document.createElement('div');
        el.className = 'tqa-toast tqa-toast--' + tone;
        el.setAttribute('role', tone === 'err' ? 'alert' : 'status');

        var box = document.createElement('span');
        box.className = 'tqa-toast__icon';
        box.innerHTML = icon(tone);

        var body = document.createElement('div');
        body.className = 'tqa-toast__body';
        if (opts.title) {
            var b = document.createElement('b');
            b.textContent = opts.title;
            body.appendChild(b);
        }
        var p = document.createElement('span');
        p.textContent = message;          // نص لا HTML — عمدا
        body.appendChild(p);

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'tqa-toast__x';
        close.setAttribute('aria-label', TQ.t('إغلاق الرسالة'));
        close.innerHTML = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none"' +
                          ' stroke="currentColor" stroke-width="2" stroke-linecap="round"' +
                          ' aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>';

        el.appendChild(box);
        el.appendChild(body);
        el.appendChild(close);
        host().appendChild(el);

        /* المهلة تطول مع طول النص: رسالة من سطرين تقرأ في أربع ثوان لا في
           ثانيتين، والرسالة التي تختفي قبل أن تقرأ لم ترسل. */
        var ms = opts.timeout;
        if (typeof ms !== 'number') {
            ms = Math.min(12000, Math.max(4500, message.length * 70));
            if (tone === 'err') ms += 2500;
        }

        var timer = null, gone = false;
        function dismiss() {
            if (gone) return;
            gone = true;
            clearTimeout(timer);
            el.classList.add('is-out');
            setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 220);
        }
        function arm() { if (ms > 0) timer = setTimeout(dismiss, ms); }

        close.addEventListener('click', dismiss);
        /* الوقوف بالمؤشر يوقف العد: من يقرأ رسالة طويلة لا تسحب من تحته. */
        el.addEventListener('mouseenter', function () { clearTimeout(timer); });
        el.addEventListener('mouseleave', arm);

        requestAnimationFrame(function () { el.classList.add('is-in'); });
        arm();

        return { close: dismiss };
    };

    /* اختصارات بأسماء تقرأ في موضع النداء */
    TQA.ok    = function (m, o) { return TQA.toast(m, 'ok', o); };
    TQA.error = function (m, o) { return TQA.toast(m, 'err', o); };
    TQA.warn  = function (m, o) { return TQA.toast(m, 'warn', o); };
    TQA.info  = function (m, o) { return TQA.toast(m, 'info', o); };

    /* =====================================================================
       ٢ · نافذة التأكيد

       ترجع `Promise<boolean>` لا تحجب الخيط كما تحجبه `confirm()` الأصلية.
       والأصلية محجوبة في بعض المتصفحات داخل إطار، وشكلها من نظام التشغيل
       لا من المنتج، ونصها لا يقبل تنسيقا ولا اتجاها.
       ===================================================================== */

    var dlg = null, dlgParts = null, dlgResolve = null, dlgOpener = null;

    function buildDialog() {
        if (dlg) return;

        dlg = document.createElement('div');
        dlg.className = 'tqa-modal';
        dlg.setAttribute('role', 'dialog');
        dlg.setAttribute('aria-modal', 'true');
        dlg.setAttribute('aria-labelledby', 'tqa-modal-t');
        dlg.setAttribute('aria-describedby', 'tqa-modal-b');
        dlg.hidden = true;

        dlg.innerHTML =
            '<div class="tqa-modal__scrim" data-x></div>' +
            '<div class="tqa-modal__box" role="document">' +
              '<span class="tqa-modal__icon" data-icon aria-hidden="true"></span>' +
              '<h2 class="tqa-modal__title" id="tqa-modal-t"></h2>' +
              '<p class="tqa-modal__body" id="tqa-modal-b"></p>' +
              '<div class="tqa-modal__acts">' +
                '<button type="button" class="tqa-btn tqa-btn--ghost" data-x></button>' +
                '<button type="button" class="tqa-btn tqa-btn--primary" data-go></button>' +
              '</div>' +
            '</div>';

        document.body.appendChild(dlg);

        dlgParts = {
            box:    dlg.querySelector('.tqa-modal__box'),
            icon:   dlg.querySelector('[data-icon]'),
            title:  dlg.querySelector('.tqa-modal__title'),
            body:   dlg.querySelector('.tqa-modal__body'),
            go:     dlg.querySelector('[data-go]'),
            cancel: dlg.querySelector('.tqa-modal__acts [data-x]')
        };

        dlg.addEventListener('click', function (e) {
            if (e.target.hasAttribute && e.target.hasAttribute('data-x')) settle(false);
        });
        dlgParts.go.addEventListener('click', function () { settle(true); });

        document.addEventListener('keydown', function (e) {
            if (dlg.hidden) return;
            if (e.key === 'Escape') { e.preventDefault(); settle(false); return; }

            /* حبس التركيز: نافذة معيارية لا تترك لوح المفاتيح يتجول في
               صفحة معطلة خلفها. */
            if (e.key !== 'Tab') return;
            var f = dlg.querySelectorAll('button:not([disabled])');
            if (!f.length) return;
            var first = f[0], last = f[f.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        });
    }

    function settle(v) {
        if (dlg.hidden) return;
        dlg.hidden = true;
        document.documentElement.classList.remove('tqa-modal-open');
        var r = dlgResolve; dlgResolve = null;
        /* التركيز يعود إلى الزر الذي فتح النافذة: من أغلقها بلوح المفاتيح
           يجد نفسه في أول الصفحة إن لم يعد. */
        if (dlgOpener && document.body.contains(dlgOpener)) { try { dlgOpener.focus(); } catch (e) {} }
        dlgOpener = null;
        if (r) r(!!v);
    }

    var ICON_TONE = {
        danger: '<path d="M4 7h16"/><path d="M9.5 7V5.2A1.2 1.2 0 0 1 10.7 4h2.6a1.2 1.2 0 0 1 1.2 1.2V7"/>' +
                '<path d="M6.5 7 7.4 19a1.6 1.6 0 0 0 1.6 1.5h6a1.6 1.6 0 0 0 1.6-1.5L17.5 7"/>',
        warn:   '<path d="M12 4 2.8 20h18.4z"/><path d="M12 10v4M12 17.2v.01"/>',
        ask:    '<circle cx="12" cy="12" r="9"/>' +
                '<path d="M9.6 9.4A2.5 2.5 0 0 1 14.4 10c0 1.7-2.4 2-2.4 3.4"/><path d="M12 17.2v.01"/>'
    };

    /**
     * يسأل قبل أن ينفذ.
     *
     * @param {object|string} o نص السؤال، أو
     *        { title, body, confirm, cancel, tone: danger|warn|ask, opener }
     * @returns {Promise<boolean>}
     */
    TQA.confirm = function (o) {
        if (typeof o === 'string') o = { body: o };
        o = o || {};
        buildDialog();

        var tone = o.tone || 'ask';
        dlgParts.icon.className = 'tqa-modal__icon tqa-modal__icon--' + tone;
        dlgParts.icon.innerHTML =
            '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor"' +
            ' stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
            (ICON_TONE[tone] || ICON_TONE.ask) + '</svg>';

        dlgParts.title.textContent  = o.title || TQ.t('تأكيد الإجراء');
        dlgParts.body.textContent   = o.body  || TQ.t('هل تريد المتابعة؟');
        dlgParts.body.hidden        = !dlgParts.body.textContent;
        dlgParts.go.textContent     = o.confirm || (tone === 'danger' ? TQ.t('نعم، احذف') : TQ.t('متابعة'));
        dlgParts.cancel.textContent = o.cancel  || TQ.t('رجوع');

        dlgParts.go.className = 'tqa-btn ' + (tone === 'danger' ? 'tqa-btn--danger' : 'tqa-btn--primary');

        dlgOpener = o.opener || document.activeElement;
        dlg.hidden = false;
        document.documentElement.classList.add('tqa-modal-open');
        /* التركيز على «رجوع» لا على «متابعة»: ضغطة مسافة تائهة لا تنبغي
           أن تحذف شيئا. */
        setTimeout(function () { dlgParts.cancel.focus(); }, 0);

        return new Promise(function (resolve) { dlgResolve = resolve; });
    };

    /* =====================================================================
       ٣ · الاستعمال المعلن: `data-tqa-confirm` على أي نموذج أو رابط أو زر

       النص في السمة، والنبرة تشتق من `data-tqa-confirm-tone` أو من كون
       الزر خطرا. والسبب في اختيار السمة على النداء المباشر: الشاشة تصف
       ما تريد، والسلوك يكتب مرة واحدة هنا — فمن أضاف زر حذف جديدا لا
       يحتاج أن يتذكر سطر جافاسكربت.
       ===================================================================== */

    function meta(el) {
        var body = el.getAttribute('data-tqa-confirm') || '';
        return {
            body:    body,
            title:   el.getAttribute('data-tqa-confirm-title') || '',
            confirm: el.getAttribute('data-tqa-confirm-ok') || '',
            tone:    el.getAttribute('data-tqa-confirm-tone') ||
                     (/tqa-btn--danger|btn-danger|text-danger/.test(el.className) ? 'danger' : 'ask')
        };
    }

    /* النماذج: الاعتراض عند `submit` في طور الالتقاط، فيسبق أي مستمع آخر. */
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!f || f.tagName !== 'FORM') return;

        /* الزر الذي أرسل النموذج قد يحمل السمة وحده: نموذج فيه «حولت»
           و«رفض» يسأل سؤالين مختلفين. */
        var btn = f.__tqaSubmitter || null;
        var src = (btn && btn.hasAttribute('data-tqa-confirm')) ? btn
                : (f.hasAttribute('data-tqa-confirm') ? f : null);
        if (!src) return;
        if (f.__tqaOk) { f.__tqaOk = false; return; }

        e.preventDefault();
        e.stopPropagation();

        var m = meta(src);
        m.opener = btn || f;
        TQA.confirm(m).then(function (yes) {
            if (!yes) return;
            f.__tqaOk = true;
            /* إعادة الإرسال بالزر نفسه: `form.submit()` تسقط `name`/`value`
               الخاصين بالزر، وشاشة طلبات السحب تميز «حولت» من «رفض» بهما
               وحدهما — فيرسل الطلب بلا قرار. */
            if (btn && (btn.name || btn.type === 'submit') && typeof btn.click === 'function') {
                btn.click();
            } else if (typeof f.requestSubmit === 'function') {
                f.requestSubmit();
            } else {
                f.submit();
            }
        });
    }, true);

    /* من ضغط الزر؟ `submitter` غير مدعوم في كل المتصفحات، فيلتقط بالنقر. */
    document.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('button, input[type=submit], input[type=image]');
        if (!b) return;
        var f = b.form || (b.closest && b.closest('form'));
        if (f) {
            f.__tqaSubmitter = b;
            setTimeout(function () { f.__tqaSubmitter = null; }, 0);
        }
    }, true);

    /* الروابط والأزرار غير المرسلة */
    document.addEventListener('click', function (e) {
        var el = e.target.closest && e.target.closest('[data-tqa-confirm]');
        if (!el) return;
        if (el.tagName === 'FORM') return;
        /* الزر داخل نموذج يعالج في حدث `submit` وحده. والفحص بـ`el.form`
           لا بـ`el.type`: نوع `<button>` الافتراضي «submit» حتى خارج أي
           نموذج، فزر مستقل يحمل السمة كان يخرج من هنا ولا يلتقطه شيء. */
        if (el.form) return;
        if (el.__tqaOk) { el.__tqaOk = false; return; }

        e.preventDefault();
        e.stopPropagation();

        var m = meta(el);
        m.opener = el;
        TQA.confirm(m).then(function (yes) {
            if (!yes) return;
            var href = el.getAttribute('href');
            if (href && href !== '#' && href.indexOf('javascript:') !== 0) {
                window.location.href = href;
                return;
            }
            el.__tqaOk = true;
            el.click();
        });
    }, true);

    /* =====================================================================
       ٤ · توكن CSRF لنداءات AJAX

       انظر TQ-CSRF-LOST في `application/views/backend/includes_top.php`:
       الربط كان هناك على نسخة jQuery الأولى، و`app.min.js` تكتب فوقها
       نسخة ثانية فيضيع المستمع — فكل POST بـAJAX في اللوحة يرد 403.

       وهذا الملف `defer`، أي ينفذ بعد كل سكربتات الجسم، فيمسك النسخة
       الأخيرة أيا كانت.
       ===================================================================== */

    function armCsrf($) {
        if (!$ || !window.TQ_CSRF || !TQ_CSRF.name) return false;
        if ($.__tqCsrfArmed) return true;
        $.__tqCsrfArmed = true;

        var NAME = TQ_CSRF.name, HASH = TQ_CSRF.hash;

        /* `ajaxPrefilter` لا `ajaxSend`: الأولى تنادى **قبل** أن يحول
           jQuery كائن `data` إلى نص، فتقبل الإضافة إليه كائنا. والثانية
           تنادى بعد التحويل، فالإضافة فيها تعتمد على أن ينسخ الناقل
           السلسلة وقت الإرسال لا وقت البناء — سلوك غير موثق. */
        $.ajaxPrefilter(function (opts, orig, xhr) {
            var m = (opts.type || opts.method || 'GET').toUpperCase();
            if (m === 'GET' || m === 'HEAD' || m === 'OPTIONS') return;
            if (opts.crossDomain) return;

            if (opts.data instanceof FormData) {
                if (!opts.data.has(NAME)) opts.data.append(NAME, HASH);
                return;
            }
            if (typeof opts.data === 'string') {
                if (opts.data.indexOf(NAME + '=') === -1) {
                    opts.data += (opts.data.length ? '&' : '') + NAME + '=' + encodeURIComponent(HASH);
                }
                return;
            }
            opts.data = $.extend({}, opts.data || {});
            if (!(NAME in opts.data)) opts.data[NAME] = HASH;
        });
        return true;
    }

    armCsrf(window.jQuery);
    /* نسخة ثالثة قد تحمل متأخرة في شاشة بعينها — فيعاد الفحص عند الجهوزية. */
    document.addEventListener('DOMContentLoaded', function () { armCsrf(window.jQuery); });

    /* =====================================================================
       ٥ · لغة DataTables

       سبعة جداول في اللوحة تبنى بـDataTables، وكلها تخرج بنصوصه
       الإنجليزية الافتراضية داخل صفحة عربية: «Search:» فوق كل جدول،
       و«Showing 1 to 10 of 20 entries» تحته، و«No data available in
       table» في الفراغ، و«Export as CSV» على الزر.

       والضبط هنا مرة واحدة لا في كل شاشة: `defaults` تقرأ عند التهيئة،
       وتهيئة كل جدول تقع في `$(document).ready` — أي بعد هذا الملف
       (`defer` ينفذ قبل `DOMContentLoaded`).
       ===================================================================== */

    (function ($) {
        if (!$ || !$.fn || !$.fn.dataTable) return;

        $.extend(true, $.fn.dataTable.defaults, {
            language: {
                sSearch:       TQ.t('ابحث:'),
                sLengthMenu:   TQ.t('اعرض _MENU_ صفا'),
                sInfo:         TQ.t('المعروض _START_ إلى _END_ من _TOTAL_'),
                sInfoEmpty:    TQ.t('لا صفوف'),
                sInfoFiltered: TQ.t('(مرشحة من _MAX_)'),
                sZeroRecords:  TQ.t('لا نتائج تطابق البحث.'),
                sEmptyTable:   TQ.t('لا بيانات بعد.'),
                sProcessing:   TQ.t('جار التحميل…'),
                sLoadingRecords: TQ.t('جار التحميل…'),
                oPaginate: { sFirst: TQ.t('الأولى'), sLast: TQ.t('الأخيرة'), sNext: TQ.t('التالي'), sPrevious: TQ.t('السابق') },
                oAria: { sSortAscending: TQ.t(': رتب تصاعديا'), sSortDescending: TQ.t(': رتب تنازليا') }
            }
        });
    })(window.jQuery);

    /* =====================================================================
       ٦ · جسور الأسماء القديمة

       ٣٨ موضعا في الشاشات الموروثة تنادي `confirm_modal(url)`، وسبعة في
       المتحكمات تولد النداء نصا. وإعادة كتابتها كلها تغيير في تسعين ملفا
       بلا أثر يراه أحد — فتعرف الأسماء نفسها هنا فوق المحرك الجديد.
       ===================================================================== */

    /** نافذة الحذف الموروثة: كانت `#alert-modal` بأيقونة dripicons ونص إنجليزي. */
    window.confirm_modal = function (url, message) {
        TQA.confirm({
            tone:    'danger',
            title:   TQ.t('تأكيد الحذف'),
            body:    message || TQ.t('هذا الإجراء لا رجعة فيه. هل تريد المتابعة؟'),
            confirm: TQ.t('نعم، نفذ')
        }).then(function (yes) { if (yes) window.location.href = url; });
    };

    /** النسخة التي تحذف بـAJAX وتخفي الصف. */
    window.ajax_confirm_modal = function (url, elem_id, message) {
        TQA.confirm({
            tone:    'danger',
            title:   TQ.t('تأكيد الحذف'),
            body:    message || TQ.t('هذا الإجراء لا رجعة فيه. هل تريد المتابعة؟'),
            confirm: TQ.t('نعم، نفذ')
        }).then(function (yes) {
            if (!yes) return;
            if (!window.jQuery) { window.location.href = url; return; }
            window.jQuery.ajax({
                url: url,
                success: function (response) {
                    var r = response;
                    try { r = JSON.parse(response); } catch (err) { r = null; }
                    if (r && r.status === 'success') {
                        var row = document.getElementById(elem_id);
                        if (row) window.jQuery(row).fadeOut(400);
                        TQA.ok(r.message || TQ.t('تم.'));
                    } else {
                        TQA.error((r && r.message) || TQ.t('تعذر تنفيذ الإجراء.'));
                    }
                },
                error: function () { TQA.error(TQ.t('تعذر الاتصال بالخادم. أعد المحاولة.')); }
            });
        });
    };

    /**
     * `$.NotificationApp` — نداءات القالب القديم كلها تمر من هنا الآن.
     * التوقيع محفوظ كما هو (`title, message, position, color, type`) لأن
     * المستدعين ثمانون ولا يقرأ أحدهم قيمة راجعة.
     */
    function bridgeNotificationApp($) {
        if (!$) return;
        $.NotificationApp = $.NotificationApp || {};
        $.NotificationApp.send = function (title, message, position, color, type) {
            /* عنوان القالب («Congratulations!» و«Oh snap!») لا يضيف شيئا
               إلى نص عربي مكتمل، فيسقط ما لم يكن مختلفا عن المعتاد. */
            var GENERIC = /^(congratulations|success|heads up|oh snap|error|warning|تهانينا|نجاح|انتباه|أوم المفاجئة|خطأ)!?$/i;
            var t = String(title || '').trim();
            return TQA.toast(message, type, { title: GENERIC.test(t) ? '' : t });
        };
    }

    if (window.jQuery) bridgeNotificationApp(window.jQuery);
    else document.addEventListener('DOMContentLoaded', function () { bridgeNotificationApp(window.jQuery); });

    /* =====================================================================
       ٧ · تفاصيل صغيرة تتكرر في كل شاشة
       ===================================================================== */

    /**
     * ترشيح خيارات منتق طويل.
     *
     * `<input data-tqa-filter="#selectId">` يخفي الخيارات التي لا تطابق
     * ما يكتب فيه. بديل عن بحث select2 — وهو غير محمل في اللوحة أصلا
     * (انظر TQ-SELECT2-GONE في `assets/backend/js/custom.js`)، فكانت
     * منتقيات المستخدمين في «تسجيل طالب» و«إرسال نشرة» **فارغة تماما**:
     * وسم `<select></select>` بلا خيار واحد، ينتظر جافاسكربت لا يوجد.
     */
    document.addEventListener('input', function (e) {
        var box = e.target;
        if (!box.hasAttribute || !box.hasAttribute('data-tqa-filter')) return;

        var sel = document.querySelector(box.getAttribute('data-tqa-filter'));
        if (!sel) return;

        var q = box.value.trim().toLowerCase();
        var shown = 0;
        Array.prototype.forEach.call(sel.options, function (o) {
            var hit = !q || o.textContent.toLowerCase().indexOf(q) !== -1;
            o.hidden = !hit;
            /* الخيار المخفي يبقى مختارا إن كان مختارا: الترشيح بحث لا حذف. */
            if (hit) shown++;
        });

        var note = box.parentNode.querySelector('[data-tqa-filter-count]');
        if (note) note.textContent = q ? TQ.t('يطابق ____', shown) : '';
    });

    document.addEventListener('DOMContentLoaded', function () {

        /* الزر الذي يرسل نموذجا يعطل بعد أول ضغطة: ضغطتان متتاليتان على
           «احفظ» تكتبان صفين، وهو أكثر ما يشكى منه في نماذج بطيئة. */
        document.addEventListener('submit', function (e) {
            var f = e.target;
            if (!f || f.tagName !== 'FORM' || f.hasAttribute('data-tqa-nolock')) return;
            var b = f.__tqaSubmitter || f.querySelector('button[type=submit], input[type=submit]');
            if (!b || b.disabled) return;
            setTimeout(function () {
                b.disabled = true;
                b.classList.add('is-busy');
                /* شبكة متعثرة لا تترك الزر معطلا إلى الأبد. */
                setTimeout(function () { b.disabled = false; b.classList.remove('is-busy'); }, 12000);
            }, 0);
        });

        /* الرسائل المرسومة في الصفحة تختفي وحدها بعد قراءتها — وتبقى
           رسالة الخطأ: من أخطأ يحتاجها أمامه حتى يصلح. */
        Array.prototype.forEach.call(document.querySelectorAll('.tqa-flash--ok'), function (el) {
            setTimeout(function () {
                el.classList.add('is-out');
                setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 300);
            }, 9000);
        });
    });

})(window, document);

/* ══════════════════════════════════════════════════════════════════
   TQ-PLAN-IMG · معاينة الصورة المختارة قبل الحفظ
   الرفع يقص إلى 3:2 على الخادم، والمعاينة هنا تقص بالنسبة نفسها
   (`object-fit: cover` في الورقة) — فما يراه المسؤول قبل الحفظ هو ما
   سيخرج بعده. وبلا معاينة يحفظ ويرجع إلى القائمة ليكتشف القص.
   وتعطيل مربع «احذف» عند اختيار ملف: اختيار بديل ومحو معا أمران
   متناقضان، وأحدهما يبطل الآخر صامتا.
   ══════════════════════════════════════════════════════════════════ */
(function () {
  var boxes = document.querySelectorAll('[data-tqa-file]');
  if (!boxes.length) return;

  Array.prototype.forEach.call(boxes, function (box) {
    var inp  = box.querySelector('[data-tqa-file-input]');
    var pane = box.querySelector('[data-tqa-file-preview]');
    var img  = box.querySelector('[data-tqa-file-img]');
    var clr  = box.querySelector('input[type=checkbox]');
    if (!inp) return;

    inp.addEventListener('change', function () {
      var f = inp.files && inp.files[0];
      if (clr) { clr.checked = false; clr.disabled = !!f; }
      if (!pane || !img) return;
      if (!f) { pane.hidden = true; img.removeAttribute('src'); return; }
      /* الرابط المؤقت يحرر بعد التحميل: تركه يبقي الملف في الذاكرة
         حتى تغلق الصفحة، وصورة من هاتف قد تكون ثمانية ميغابايت. */
      var url = URL.createObjectURL(f);
      img.onload = function () { URL.revokeObjectURL(url); };
      img.src = url;
      pane.hidden = false;
    });
  });
})();
