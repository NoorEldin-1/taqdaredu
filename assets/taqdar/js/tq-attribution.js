/* Consent-aware campaign attribution. No personal form fields or full URLs. */
(function () {
    'use strict';
    var key = 'tq_attr_v1', ttl = 30 * 86400;
    function cookie(name, value, age) {
        document.cookie = name + '=' + encodeURIComponent(value) + ';path=/;max-age=' + age + ';samesite=Lax;secure';
    }
    function read(name) {
        var part = document.cookie.split('; ').find(function (v) { return v.indexOf(name + '=') === 0; });
        try { return part ? decodeURIComponent(part.slice(name.length + 1)) : ''; } catch (_) { return ''; }
    }
    function accepted() {
        try { return localStorage.getItem('tq-cookie') === 'accepted'; } catch (_) { return read('tq_consent') === 'accepted'; }
    }
    function capture() {
        if (!accepted()) return;
        var p = new URLSearchParams(location.search), touch = {}, existing = null, now = Date.now();
        ['utm_source','utm_medium','utm_campaign','utm_term','utm_content'].forEach(function (k) {
            var v = p.get(k);
            if (v && /^[\p{L}\p{N} _.,:+\-/]{1,100}$/u.test(v)) touch[k] = v;
        });
        try { existing = JSON.parse(read(key)); } catch (_) {}
        if (Object.keys(touch).length) {
            touch.at = now;
            var first = existing && existing.first && now - existing.first.at < ttl * 1000 ? existing.first : touch;
            cookie(key, JSON.stringify({first:first,last:touch}), ttl);
        }
        var clid = p.get('fbclid');
        if (clid && /^[A-Za-z0-9_-]{1,200}$/.test(clid)) cookie('tq_fbc_v1', 'fb.1.' + now + '.' + clid, ttl);
    }
    window.addEventListener('tq-consent-change', function (e) {
        if (e.detail === 'accepted') capture();
        else { cookie(key, '', 0); cookie('tq_fbc_v1', '', 0); }
    });
    capture();
})();
