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
   TQ-PLAN-IMG · معاينة الصورة المختارة — انتقلت

   كانت هنا كتلة تعاين ما اختير وتعطل مربع «احذف» معه، وهي تعرف
   `[data-tqa-file]` وحده — أي حقل الوحدة الموصوفة. وحقول الملفات في
   اللوحة ثلاثون في ثلاثين شاشة، ونصفها بلا معاينة ولا مربع محو ولا
   شيء يقول ما هو محفوظ الآن.

   فصار الحقل كله مكونا واحدا: [assets/taqdar/js/tqa-file.js] — يمسح
   كل `input[type=file]` ويبني الصندوق نفسه، والمعاينة والقص والمحو
   والتراجع فيه. وهي القاعدة نفسها التي جعلت قائمة الصف دالة واحدة:
   **ما يتكرر في ثلاثين شاشة يعرف مرة**.
   ══════════════════════════════════════════════════════════════════ */

/* ══════════════════════════════════════════════════════════════════
   TQ-CARD-BLIND · بطاقة الجوال بلا تسميات

   دون ٦٤٠ بكسلا يصير كل جدول `tqa-table` بطاقات: الرأس يخفى، وكل خلية
   تطبع تسميتها من `data-label` ثم قيمتها. وهو يعمل حيث كتبت الصفة —
   وأربع شاشات لم تكتبها (`enrol_history` · `instructors_pending_blog` ·
   `tqa_mail` · `tqa_whatsapp`)، فتخرج البطاقة عمودا من قيم عارية:
   تاريخ ورقم واسم بلا كلمة تقول ما هو أي منها.

   والعلاج هنا لا في الشاشات الأربع: التسمية موجودة أصلا في `thead` بنفس
   ترتيب الخلية، فتنسخ منها. فالشاشة الخامسة التي تكتب جدولا غدا تحصل
   على بطاقة صحيحة بلا أن يتذكر كاتبها الصفة — وما كتبها بيده لا يمس.
   ══════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    function label(table) {
        var head = table.tHead && table.tHead.rows[0];
        if (!head) return;

        var names = Array.prototype.map.call(head.cells, function (th) {
            /* `<span class="tqa-sr">إجراءات</span>` تسمية لقارئ الشاشة،
               وهي التسمية الصحيحة هنا كذلك. والرأس الفارغ يبقى فارغا:
               تسمية مخترعة أسوأ من لا تسمية. */
            return (th.textContent || '').replace(/\s+/g, ' ').trim();
        });

        Array.prototype.forEach.call(table.tBodies, function (body) {
            Array.prototype.forEach.call(body.rows, function (row) {
                Array.prototype.forEach.call(row.cells, function (cell, i) {
                    if (cell.hasAttribute('data-label')) return;
                    /* صف يمتد على الجدول كله (رسالة «لا نتائج») لا عمود
                       له، فلا تسمية له. */
                    if (cell.colSpan > 1) return;
                    var n = names[i];
                    if (n) cell.setAttribute('data-label', n);
                });
            });
        });
    }

    function run() {
        Array.prototype.forEach.call(document.querySelectorAll('table.tqa-table'), label);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();

/* ══════════════════════════════════════════════════════════════════
   TQ-FORM-DIRTY · التعديل الذي لم يحفظ يقال قبل أن يضيع

   نموذج الباقة ستة عشر حقلا، وشاشة الكورس تسعة تبويبات، وشاشة المعلم
   بضعة عشر حقلا. ومن عدل ثم ضغط بندا في الشريط الجانبي — أو زر الرجوع —
   يفقد كل ما كتب بلا سؤال ولا أثر: لا رسالة، ولا مسودة، ولا شيء في
   الشاشة التالية يقول إن شيئا ضاع.

   وهما إشارتان لا واحدة:

     ١ — **في الشريط** (`.tqa-formbar__dirty`) وهو ظاهر دائما: «فيه
         تعديل لم يحفظ» تقرأ **قبل** قرار المغادرة لا بعده.
     ٢ — **عند المغادرة** نافذة تقدر (`TQA.confirm`) — انظر أدناه.

   والحارس يرفع نفسه عند الإرسال: تحذير يظهر بعد ضغط «احفظ» يقرأ عطلا.

   ── TQ-LEAVE-NATIVE — ولماذا لا يكفي `beforeunload` ────────────────

   `beforeunload` يرسم **صندوق المتصفح**: «Leave site? / Changes you
   made may not be saved.» — نص إنجليزي ثابت لا تملك تغييره (المواصفة
   نفسها تلزم المتصفحات بتجاهل نصك منذ ٢٠١٦)، بلا هوية ولا اتجاه ولا
   ذكر لما ستفقده، وزراه «Leave» و«Cancel» لا يقولان أي منهما يحفظ.
   فمن ضغط «إلغاء» في شريط الحفظ — وهو **يقصد الرجوع** — يقابله سؤال
   إنجليزي عن شيء آخر بكلمة «Cancel» تعني عنده ما ضغطه للتو.

   ولا يمكن **استبداله**: الحدث يقع بعد أن يبدأ المتصفح المغادرة، وما
   يعرض فيه ليس لنا. فالعلاج أن نسبقه: كل مغادرة **نملك اعتراضها**
   (نقرة على رابط — وهي كل «إلغاء» وكل بند في الشريط الجانبي وكل
   «رجوع إلى القائمة») تعترض هنا، ويسأل عنها سؤال عربي يقول ما يضيع،
   ثم ينزع الحارس ويغادر. فلا يبلغ الصندوق الأصلي إلا ما لا نملكه:
   إغلاق اللسان، وزر الرجوع، وكتابة عنوان في الشريط. وذلك موضعه
   الصحيح — هناك لا شيء ينوب عنه.
   ══════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    var forms = document.querySelectorAll('form[data-tqa-dirty]');
    if (!forms.length) return;

    var live = [];   /* النماذج التي فيها تعديل لم يحفظ */

    function dirtyNow() {
        for (var i = 0; i < live.length; i++) if (live[i]()) return true;
        return false;
    }

    Array.prototype.forEach.call(forms, function (form) {
        var dirty = false;
        var saving = false;

        function mark() {
            if (dirty) return;
            dirty = true;
            form.classList.add('is-dirty');
        }

        /* `input` للكتابة و`change` للمنتقي ومربع الاختيار وحقل الملف —
           ولا يكفي أحدهما: `input` لا يقع على `<select>` في كل متصفح،
           و`change` لا يقع على حقل نص إلا بعد مغادرته. */
        form.addEventListener('input', mark);
        form.addEventListener('change', mark);

        form.addEventListener('submit', function () {
            saving = true;
            dirty = false;
            form.classList.remove('is-dirty');
        });

        live.push(function () { return dirty && !saving; });
    });

    /* ---- المغادرة التي نملك اعتراضها ----
       طور الفقاعة لا الالتقاط: حارس `data-tqa-confirm` يلتقط في طور
       الالتقاط ويوقف الانتشار، فبند الحذف يسأل سؤاله وحده — وسؤالان
       متتاليان عن ضغطة واحدة أسوأ من واحد. */
    var leaving = false;

    document.addEventListener('click', function (e) {
        if (leaving || !dirtyNow()) return;

        var a = e.target.closest ? e.target.closest('a[href]') : null;
        if (!a) return;
        if (a.hasAttribute('data-tqa-confirm')) return;      /* له سؤاله */
        if (a.hasAttribute('download')) return;
        if (a.target && a.target !== '_self') return;         /* لسان آخر لا يغادر */
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;

        var href = a.getAttribute('href') || '';
        if (!href || href.charAt(0) === '#' || /^(javascript|mailto|tel):/i.test(href)) return;
        /* رابط إلى موضع في الصفحة نفسها لا يغادرها. */
        if (a.href.split('#')[0] === window.location.href.split('#')[0]) return;

        e.preventDefault();

        TQA.confirm({
            tone:    'warn',
            title:   TQ.t('تعديل لم يحفظ'),
            body:    TQ.t('غادرت هذه الصفحة الآن فسيضيع ما كتبته ولم تحفظه. لا مسودة تحفظ، ولا رجعة بعد المغادرة.'),
            confirm: TQ.t('غادر بلا حفظ'),
            cancel:  TQ.t('ابق في الصفحة'),
            opener:  a
        }).then(function (yes) {
            if (!yes) return;
            /* ينزع الحارس ثم يغادر: بلا نزعه يسأل صندوق المتصفح السؤال
               نفسه بالإنجليزية بعد أن أجيب عنه بالعربية. */
            leaving = true;
            window.location.href = a.href;
        });
    });

    /* ---- ما لا نملك اعتراضه ----
       إغلاق اللسان وزر الرجوع وكتابة عنوان. والمتصفحات الحديثة تعرض
       نصها هي لا نصنا، والمطلوب `preventDefault` وقيمة عائدة معا
       لتغطية القديم منها. */
    window.addEventListener('beforeunload', function (e) {
        if (leaving || !dirtyNow()) return;
        e.preventDefault();
        e.returnValue = '';
        return '';
    });
})();

/* ══════════════════════════════════════════════════════════════════
   TQ-ROW-CLUTTER · قائمة الصف — محركها

   الوسم يبنيه `tqa_rowmenu()` في [taqdar_admin_helper.php]، وهذا يفتحه
   ويغلقه ويضعه في موضعه. وثلاث قواعد تحكمه:

   ١ — **الموضع يحسب هنا لا في الورقة.** اللوح `position: fixed` لأن
       `.tqa-table__wrap` عليها `overflow: auto` تقص كل مطلق؛ والثابت
       بلا إحداثيات يقف في ركن الشاشة. فيقاس مستطيل الزر ويوضع اللوح
       تحته، وينقلب فوقه إن لم يبق تحته مكان — وقائمة الصف الأخير هي
       الحالة الشائعة لا النادرة.

   ٢ — **الطبقة هي ما يغلق بالنقر خارجها، لا مستمع على المستند.**
       المستمع العام يلتقط النقرة **بعد** أن تصل إلى ما تحتها، فمن ضغط
       زر صف آخر فتح قائمته ثم أغلقها في اللحظة نفسها. والطبقة تبتلع
       النقرة الأولى — وهو سلوك القائمة في كل نظام.

   ٣ — **لا يغلق بالنقر داخله.** فيه حقول تكتب (مرجع الحوالة) وقوائم
       تقرأ (قسمة الإيراد)، وقائمة تغلق عند أول نقرة داخلها تجعل كتابة
       المرجع مستحيلة.
   ══════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    var open = null;   /* .tqa-menu المفتوحة الآن */
    var veil = null;
    var GAP  = 6;
    var EDGE = 8;

    function sheet() { return window.innerWidth < 640; }

    function pop(menu)     { return menu.querySelector('.tqa-menu__pop'); }
    function trigger(menu) { return menu.querySelector('.tqa-menu__trigger'); }

    /* الموضع: تحت الزر ومحاذى لطرفه المنطقي.

       والمحاذاة على الطرف **المنتهي** في العربية: عمود الإجراءات آخر
       عمود، أي على يسار الشاشة في صفحة عربية — فقائمة تبدأ من يمين
       الزر وتمتد يسارا تخرج من الشاشة. والقص إلى داخل الإطار بعده
       شبكة أمان لا بديل عنه. */
    function place(menu) {
        var p = pop(menu), t = trigger(menu);
        if (!p || !t) return;

        if (sheet()) { p.classList.add('is-sheet'); p.style.top = p.style.left = ''; return; }
        p.classList.remove('is-sheet');

        /* القياس يحتاج اللوح معروضا: `hidden` يجعل أبعاده صفرا. */
        var r  = t.getBoundingClientRect();
        var w  = p.offsetWidth;
        var h  = p.offsetHeight;
        var rtl = (document.documentElement.getAttribute('dir') || '').toLowerCase() === 'rtl'
               || getComputedStyle(document.documentElement).direction === 'rtl';

        var left = rtl ? (r.right - w) : r.left;
        left = Math.max(EDGE, Math.min(left, window.innerWidth - w - EDGE));

        var top = r.bottom + GAP;
        if (top + h > window.innerHeight - EDGE) {
            var up = r.top - h - GAP;
            /* فوق الزر إن اتسع، وإلا ألصق بالقاع مع حد للارتفاع: لوح
               يخرج نصفه من الشاشة لا يمرر إليه شيء. */
            if (up >= EDGE) top = up;
            else {
                top = EDGE;
                p.style.maxBlockSize = (window.innerHeight - EDGE * 2) + 'px';
            }
        }
        p.style.top  = Math.round(top) + 'px';
        p.style.left = Math.round(left) + 'px';
    }

    function shut(back) {
        if (!open) return;
        var menu = open, t = trigger(menu), p = pop(menu);
        open = null;
        menu.classList.remove('is-open');
        if (t) t.setAttribute('aria-expanded', 'false');
        if (p) { p.hidden = true; p.style.maxBlockSize = ''; }
        if (veil) { veil.remove(); veil = null; }
        /* التركيز يعود إلى الزر متى أغلق بلوحة المفاتيح لا متى ضغط
           المستخدم في مكان آخر: إعادته دائما تسحب الصفحة إلى الجدول
           كلما نقر أحد في الفراغ. */
        if (back && t) t.focus();
    }

    function show(menu) {
        if (open === menu) { shut(true); return; }
        shut(false);

        var t = trigger(menu), p = pop(menu);
        if (!p || !t) return;

        veil = document.createElement('div');
        veil.className = 'tqa-menu-veil';
        veil.addEventListener('mousedown', function (e) { e.preventDefault(); shut(false); });
        document.body.appendChild(veil);

        p.hidden = false;
        menu.classList.add('is-open');
        t.setAttribute('aria-expanded', 'true');
        open = menu;
        place(menu);

        /* أول بند يستقبل التركيز، فلوحة المفاتيح تعمل من الضغطة
           الأولى. والحقل إن كان أول ما في اللوح فهو المقصود أصلا. */
        var first = p.querySelector('input:not([type=hidden]), .tqa-menu__item');
        if (first) setTimeout(function () { first.focus(); }, 0);
    }

    /* ---- الفتح ---- */
    document.addEventListener('click', function (e) {
        var t = e.target.closest ? e.target.closest('.tqa-menu__trigger') : null;
        if (!t) return;
        e.preventDefault();
        var menu = t.closest('.tqa-menu');
        if (menu) show(menu);
    });

    /* والنقر **داخل** اللوح لا يغلقه بلا سطر واحد: الطبقة تحته
       (`z-index` ١٢٨٠ مقابل ١٢٩٠)، فنقرة على حقل «مرجع الحوالة» تصيب
       الحقل ولا تبلغ الطبقة أصلا. وحارس يعترض `mousedown` في طور
       الالتقاط ليمنع ذلك كان يمنع الحقل من استقبال الحدث كذلك. */

    /* ---- لوحة المفاتيح ---- */
    document.addEventListener('keydown', function (e) {
        if (!open) return;

        if (e.key === 'Escape') { e.preventDefault(); shut(true); return; }

        if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
        var p = pop(open);
        if (!p) return;
        /* الحقول تتخطى بالأسهم: من يكتب في «مرجع الحوالة» يحرك مؤشره
           لا يتنقل بين البنود. */
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

        var items = Array.prototype.slice.call(p.querySelectorAll('.tqa-menu__item:not([aria-disabled=true])'));
        if (!items.length) return;
        e.preventDefault();
        var i = items.indexOf(document.activeElement);
        var n = e.key === 'ArrowDown' ? i + 1 : i - 1;
        if (n < 0) n = items.length - 1;
        if (n >= items.length) n = 0;
        items[n].focus();
    });

    /* ---- الإرسال يغلق ----
       النموذج يذهب إلى الخادم، ولوح معلق فوق صفحة تعيد تحميل نفسها يقرأ
       تعليقا. والمستمع في طور الفقاعة، فهو لا يبلغ النماذج التي تحمل
       `data-tqa-confirm`: حارسها يوقف الانتشار في طور الالتقاط. وهو
       الصواب هناك — نافذة التأكيد فوق القائمة (١٣٠٠ مقابل ١٢٩٠)، ومن
       ضغط «رجوع» يجد قائمته كما تركها بدل أن يعيد فتحها ليختار غير ما
       اختار. */
    document.addEventListener('submit', function (e) {
        if (open && pop(open) && pop(open).contains(e.target)) shut(false);
    });

    /* ---- الموضع يتبع الصفحة ----
       التقاط (`true`) لأن الجدول نفسه حاوية تمرر، وحدث تمررها لا
       يصعد إلى النافذة. ويغلق حين يخرج زره من الإطار: لوح معلق فوق
       صف لا يرى يشير إلى لا شيء. */
    function follow() {
        if (!open) return;
        var t = trigger(open);
        if (!t) return;
        var r = t.getBoundingClientRect();
        if (r.bottom < 0 || r.top > window.innerHeight) { shut(false); return; }
        place(open);
    }
    window.addEventListener('scroll', follow, true);
    window.addEventListener('resize', function () { if (open) place(open); });
})();
