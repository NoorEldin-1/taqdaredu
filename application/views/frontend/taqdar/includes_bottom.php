<?php /* TQ-I18N — `tq-i18n.js` ليس هنا: انظر `includes_top.php`.
         كان `defer` في هذا الذيل، والمؤجل ينفذ **بعد أن يفرغ تحليل
         المستند** — أي بعد كل `<script>` سطري في القوالب. وشاشات
         البوابة تكتب سكربتها سطريا وتناديه في حينه (`load()` في آخر
         `tq_mistakes.php` و`tq_mastery.php`)، فتقرأ `TQ` وهي `undefined`.
         وما دام السطري لا ينادي إلا `fetch` لم يظهر شيء؛ فأول نداء
         لـ`TQ.gateFetch` منه رمى `TypeError` صامتا وبقيت الشاشة على
         هيكلها العظمي إلى الأبد — بلا خطأ يعرض ولا زر يعاد به.
         والملف يعد في رأسه أنه «يحمل قبل كل سكربت آخر»، وهذا يفي بوعده. */ ?>
<script src="<?php echo tq_asset('js/tq-phone.js'); ?>" defer></script>
<script src="<?php echo tq_asset('js/taqdar.js'); ?>" defer></script>

<?php /* TQA-SELECT · TQA-FILE — المكونان اللذان بنيا للوحة، بلا نسخة
         ثانية: كلاهما يمسح `document` ولا يشترط `body.tqa`، فالسطحان
         سواء عندهما. وورقتهما `css/tqa-controls.css` في الرأس.
         و`defer` بعد `taqdar.js`: ذاك يبني درج القائمة وقد يحقن حقولا. */ ?>
<script src="<?php echo tq_asset('js/tqa-select.js'); ?>" defer></script>
<script src="<?php echo tq_asset('js/tqa-file.js'); ?>" defer></script>

<?php /* سكربت المشغل يحمل على صفحته وحدها — لا يثقل بقية الشاشات.
         و`tq-player.js` **قبله**: `taqdar-lesson.js` ينادي `TQPlayer`
         عند أول رسم، وكلاهما `defer` فينفذان بترتيب ورودهما. */ ?>
<?php if (isset($page_name) && $page_name === 'tq_lesson'): ?>
<script src="<?php echo tq_asset('js/tq-player.js'); ?>" defer></script>
<script src="<?php echo tq_asset('js/taqdar-lesson.js'); ?>" defer></script>
<?php endif; ?>

<?php /* وشاشة المراجعة كذلك — سكربتها لا يحمل إلا حيث يستعمل. */ ?>
<?php if (isset($page_name) && $page_name === 'tq_reviews'): ?>
<script src="<?php echo tq_asset('js/taqdar-reviews.js'); ?>" defer></script>
<?php endif; ?>

<?php /* TQ-PREVIEW-CAP — حاجز الخمس دقائق. صفحة المعاينة وحدها: لا
         يحمل على مشغل الدرس المدفوع فيقف على من دفع. */ ?>
<?php if (isset($page_name) && $page_name === 'site_preview'): ?>
<script src="<?php echo tq_asset('js/tq-preview-gate.js'); ?>" defer></script>
<?php endif; ?>

<?php /* واجهة تقدر بلا jQuery وتستعمل fetch — فيلف fetch نفسه.
       اللف لنفس الأصل وطرق الكتابة وحدها: توكن يرسل إلى طرف ثالث
       تسريب لسر الجلسة لا حماية. */ ?>
<?php if (config_item('csrf_protection')): ?>
<script>
(function () {
    var NAME = "<?php echo $this->security->get_csrf_token_name(); ?>";
    var HASH = "<?php echo $this->security->get_csrf_hash(); ?>";
    var WRITE = { POST: 1, PUT: 1, PATCH: 1, DELETE: 1 };
    var origFetch = window.fetch;

    function sameOrigin(url) {
        try { return new URL(url, location.href).origin === location.origin; }
        catch (e) { return false; }
    }

    if (origFetch) window.fetch = function (input, init) {
        init = init || {};
        var url = (typeof input === "string") ? input : (input && input.url) || "";
        var method = (init.method || (input && input.method) || "GET").toUpperCase();

        if (WRITE[method] && sameOrigin(url)) {
            /* الترويسة تحمل التوكن في كل طلب كتابة لنفس الأصل.
               وهي الطريق الذي يصل: `$_POST` لا يملأ لطلب JSON، فالتوكن
               المحقون داخل الجسم كان يرسل ولا يقرأ — و`REST_Input` يرفعه
               من هنا (أو من الجسم) إلى `$_POST` قبل فحص CodeIgniter. */
            init.headers = init.headers || {};
            if (init.headers instanceof Headers) {
                if (!init.headers.has("X-CSRF-Token")) init.headers.set("X-CSRF-Token", HASH);
            } else if (!init.headers["X-CSRF-Token"]) {
                init.headers["X-CSRF-Token"] = HASH;
            }

            var b = init.body;
            if (b instanceof FormData) {
                if (!b.has(NAME)) b.append(NAME, HASH);
            } else if (typeof b === "string") {
                var isJson = false;
                try { JSON.parse(b); isJson = true; } catch (e) {}
                if (isJson) {
                    var o = JSON.parse(b);
                    if (o && typeof o === "object" && !(NAME in o)) { o[NAME] = HASH; init.body = JSON.stringify(o); }
                } else if (b.indexOf(NAME + "=") === -1) {
                    init.body = b + (b.length ? "&" : "") + NAME + "=" + encodeURIComponent(HASH);
                }
            } else if (b === undefined || b === null) {
                init.body = NAME + "=" + encodeURIComponent(HASH);
                init.headers = init.headers || {};
                if (!init.headers["Content-Type"] && !init.headers["content-type"]) {
                    init.headers["Content-Type"] = "application/x-www-form-urlencoded";
                }
            }
        }
        return origFetch.call(this, input, init);
    };

    /* نماذج HTML العادية — شبكة الأمان.
       CI3 يشترط الرمز في كل جسم POST، و`form_open()` وحده يحقنه. وفي هذه
       الشجرة مئتان واثنان وثلاثون نموذجا مكتوبا بيده مقابل نموذجين اثنين
       بـ`form_open()`، فكل «حفظ» و«إرسال» فيها كان يرد 403 بصفحة إنجليزية
       خام لا رسالة عربية ولا حفظ. والحقن هنا عند الإرسال لا عند التحميل:
       نموذج يبنيه سكربت بعد التحميل يلتقط كذلك.

       وهذا احتياط لا أصل: نماذج تقدر تطبع الحقل بنفسها عبر `tq_csrf()`،
       لأن من أوقف JS يجب أن يحفظ كما يحفظ غيره. */
    document.addEventListener("submit", function (e) {
        var f = e.target;
        if (!f || f.tagName !== "FORM") return;
        if ((f.method || "get").toUpperCase() !== "POST") return;
        if (!sameOrigin(f.getAttribute("action") || location.href)) return;
        if (f.querySelector('input[name="' + NAME + '"]')) return;

        var i = document.createElement("input");
        i.type = "hidden";
        i.name = NAME;
        i.value = HASH;
        f.appendChild(i);
    }, true);

    /* XMLHttpRequest لمن يستعمله مباشرة */
    var open = XMLHttpRequest.prototype.open, send = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.open = function (m, u) {
        this.__tqWrite = !!WRITE[(m || "").toUpperCase()] && sameOrigin(u);
        return open.apply(this, arguments);
    };
    XMLHttpRequest.prototype.send = function (body) {
        if (this.__tqWrite) {
            try { this.setRequestHeader("X-CSRF-Token", HASH); } catch (e) {}
            if (body instanceof FormData) {
                if (!body.has(NAME)) body.append(NAME, HASH);
            } else if (typeof body === "string" && body.indexOf(NAME + "=") === -1) {
                body = body + (body.length ? "&" : "") + NAME + "=" + encodeURIComponent(HASH);
            }
        }
        return send.call(this, body);
    };
})();
</script>
<?php endif; ?>
