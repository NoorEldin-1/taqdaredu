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

    /* اللوحة تنادي `TQA` لا `TQ` — والدالة واحدة، فلا نسخة ثانية. */
    var TQA = window.TQA = window.TQA || {};
    if (!TQA.t) TQA.t = t;
})();
