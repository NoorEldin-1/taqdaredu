/**
 * TQ-I18N — الترجمة في المتصفح.
 *
 * نصوص السكربتات مكتوبة فيها عربية: «تعذر الحفظ»، «هل أنت متأكد؟»،
 * «انتهت جلستك». وهي أخطر نص في الشاشة — نافذة التأكيد التي تسبق حذفا لا
 * يرجع. فترك السكربتات بلا ترجمة يعني لوحة إنجليزية تسأل بالعربية قبل أن
 * تحذف، ومن لا يقرأ السؤال يضغط «نعم».
 *
 * والقاعدة هنا **هي قاعدة PHP نفسها**: النص العربي مفتاح، وما لا مدخل له
 * في القاموس يرد كما كتب. والقاموس واحد لا نسختين — يطبعه `tq_i18n_js()`
 * في رأس الصفحة من ملف `application/language/tq/<لغة>/js.php`، فلا ملف
 * `.js` ثان يفترق عن أخيه عند أول تعديل.
 *
 * يحمل **قبل** كل سكربت آخر ويعرف `window.TQ` مبكرا، فالملفات التي تنادي
 * `TQ.t()` عند تحميلها لا تسبقه.
 */
(function () {
    'use strict';

    var state = window.TQ_I18N || { lang: 'arabic', dir: 'rtl', iso: 'ar', map: {} };
    var map = state.map || {};

    /** تسوية المفتاح — تطابق `tq_i18n_key()` في PHP حرفا بحرف. */
    function key(s) {
        return String(s).replace(/ /g, ' ').replace(/\s+/g, ' ').trim();
    }

    /**
     * يترجم، ويبدل `____` بالبدائل بالترتيب.
     *
     * والعلامة `____` هي علامة `t()` في PHP نفسها: اصطلاحان لشيء واحد
     * يجعلان النص الواحد يكتب مرتين في القاموس بصورتين.
     */
    function t(s) {
        var out = map[key(s)];
        if (out === undefined) out = String(s);

        if (arguments.length > 1) {
            for (var i = 1; i < arguments.length; i++) {
                out = out.replace('____', String(arguments[i]));
            }
        }
        return out;
    }

    var TQ = window.TQ = window.TQ || {};
    TQ.t    = t;
    TQ.lang = state.lang;
    TQ.dir  = state.dir;
    TQ.iso  = state.iso;
    TQ.isRtl = state.dir === 'rtl';

    /**
     * TQ-RAW-ERROR — الخطأ يقال بلغة صاحبه، لا بلغة المتصفح.
     *
     * الشاشات التي تنادي `taqdar_gate` كانت تكتب `e.message` في اللوح
     * حرفا. وذلك يصلح للخطأ الذي **يصنعه الخادم** — فهو يرد
     * `error.message_ar` عربية مفهومة. أما الطريقان الآخران فلا:
     *
     * · `fetch` يرفض (شبكة مقطوعة، خادم لا يرد) فيرمي `TypeError`
     *   رسالته «Failed to fetch» — إنجليزية، من المتصفح، وقرأها طالب
     *   وسط شاشة عربية ولا تقول له أن يتحقق من اتصاله.
     * · `r.json()` يفشل حين يرد الخادم HTML بدل JSON — وهو ما يقع عند
     *   كل خطأ PHP قاتل وعند انتهاء الجلسة إن أعيد التوجيه إلى صفحة
     *   الدخول — فتقرأ «Unexpected token < in JSON at position 0».
     *
     * والثلاثة تعالج بغير ما يعالج به بعضها: الأول ينتظر ويعيد، والثاني
     * يسجل دخوله من جديد، والثالث يبلغ الدعم. فرسالة واحدة لثلاثتها
     * تترك اثنين منهم يعيدان المحاولة بلا جدوى.
     *
     * @param {string|null} kind  'offline' أو 'parse' أو null
     * @param {number}      status رمز الرد إن عرف
     */
    /* `TQ.t` لا `t` وإن كانتا الدالة نفسها: `scripts/i18n_skeleton.php`
       يجرد نصوص السكربتات بالنمط `TQ.t(` وحده، فنداء `t()` العارية يبقى
       خارج `js.php` ولا يترجم أبدا — ويعرض عربيا وسط لوحة إنجليزية بلا
       أن يخطئ شيء. و`TQ` معرفة وقت **النداء** لا وقت التعريف، فآمنة. */
    function netMessage(kind, status) {
        status = status || 0;

        if (kind === 'offline') {
            return (typeof navigator !== 'undefined' && navigator.onLine === false)
                ? TQ.t('لا اتصال بالإنترنت. تحقق من اتصالك ثم أعد المحاولة.')
                : TQ.t('تعذر الوصول إلى الخادم. تحقق من اتصالك ثم أعد المحاولة.');
        }
        if (status === 401 || status === 403) {
            return TQ.t('انتهت جلستك. سجل دخولك من جديد ثم أعد المحاولة.');
        }
        if (status === 404) {
            return TQ.t('لم يعد هذا العنصر موجودا. حدث الصفحة.');
        }
        if (status === 429) {
            return TQ.t('طلبات كثيرة في وقت قصير. انتظر قليلا ثم أعد المحاولة.');
        }
        if (status >= 500) {
            return TQ.t('تعثر الخادم. أعد المحاولة بعد قليل، وإن تكرر فأبلغ الدعم.');
        }
        return TQ.t('تعذر إتمام الطلب.');
    }

    /**
     * يغلف `fetch` على بوابة الإتقان: الرد الناجح يرد كما هو، وكل تعثر
     * يخرج **رسالة عربية جاهزة للعرض** في `Error.message`. فالشاشة تكتبها
     * كما هي ولا تفرع، ولا يبقى في المستودع موضع يطبع نص المتصفح.
     */
    function gateFetch(url, opt) {
        return fetch(url, opt).then(function (r) {
            return r.json().then(
                function (j) {
                    if (!r.ok || (j && j.error)) {
                        var e = (j && j.error) || {};
                        var err = new Error(
                            e.message_ar || e.message || netMessage(null, r.status));
                        err.code = e.code || ('HTTP_' + r.status);
                        err.status = r.status;
                        err.details = e.details || {};
                        throw err;
                    }
                    return j;
                },
                function () {
                    /* رد ليس JSON — صفحة خطأ أو تحويل إلى الدخول. */
                    var err = new Error(netMessage('parse', r.status));
                    err.code = 'BAD_RESPONSE';
                    err.status = r.status;
                    throw err;
                }
            );
        }, function () {
            var err = new Error(netMessage('offline'));
            err.code = 'OFFLINE';
            err.status = 0;
            throw err;
        });
    }

    TQ.netMessage = netMessage;
    TQ.gateFetch  = gateFetch;

    /* اللوحة تنادي `TQA` لا `TQ` — والدالة واحدة، فلا نسخة ثانية. */
    var TQA = window.TQA = window.TQA || {};
    if (!TQA.t) TQA.t = t;
})();
