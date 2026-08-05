<script src="<?php echo tq_asset('js/taqdar.js'); ?>" defer></script>

<?php /* سكربت المشغّل يُحمَّل على صفحته وحدها — لا يُثقل بقيّة الشاشات. */ ?>
<?php if (isset($page_name) && $page_name === 'tq_lesson'): ?>
<script src="<?php echo tq_asset('js/taqdar-lesson.js'); ?>" defer></script>
<?php endif; ?>

<?php /* وشاشة المراجعة كذلك — سكربتها لا يُحمَّل إلّا حيث يُستعمل. */ ?>
<?php if (isset($page_name) && $page_name === 'tq_reviews'): ?>
<script src="<?php echo tq_asset('js/taqdar-reviews.js'); ?>" defer></script>
<?php endif; ?>

<?php /* واجهة تقدّر بلا jQuery وتستعمل fetch — فيُلفّ fetch نفسه.
       اللفّ لنفس الأصل وطرق الكتابة وحدها: توكنٌ يُرسَل إلى طرف ثالث
       تسريبٌ لسرّ الجلسة لا حماية. */ ?>
<?php if (config_item('csrf_protection')): ?>
<script>
(function () {
    var NAME = "<?php echo $this->security->get_csrf_token_name(); ?>";
    var HASH = "<?php echo $this->security->get_csrf_hash(); ?>";
    var WRITE = { POST: 1, PUT: 1, PATCH: 1, DELETE: 1 };
    var origFetch = window.fetch;
    if (!origFetch) return;

    function sameOrigin(url) {
        try { return new URL(url, location.href).origin === location.origin; }
        catch (e) { return false; }
    }

    window.fetch = function (input, init) {
        init = init || {};
        var url = (typeof input === "string") ? input : (input && input.url) || "";
        var method = (init.method || (input && input.method) || "GET").toUpperCase();

        if (WRITE[method] && sameOrigin(url)) {
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

    /* XMLHttpRequest لمن يستعمله مباشرةً */
    var open = XMLHttpRequest.prototype.open, send = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.open = function (m, u) {
        this.__tqWrite = !!WRITE[(m || "").toUpperCase()] && sameOrigin(u);
        return open.apply(this, arguments);
    };
    XMLHttpRequest.prototype.send = function (body) {
        if (this.__tqWrite) {
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
