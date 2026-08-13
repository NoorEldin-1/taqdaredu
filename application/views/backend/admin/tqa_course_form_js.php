<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<script>
/**
 * سلوك نموذج الكورس — مشترك بين الإضافة والتحرير.
 *
 * ثلاثة إظهارات مشروطة وحساب واحد. وكلها تحسين لا شرط: النموذج يحفظ
 * كاملا بلا جافاسكربت (كل الحقول في الشجرة، والمخفي منها يرسل قيمته
 * الفارغة كما كان يفعل الظاهر).
 *
 * وما كان قبلها: `togglePriceFields` و`checkExpiryPeriod` و
 * `calculateDiscountPercentage` — ثلاث دوال عامة معلقة على `onclick`
 * و`onkeyup` في السطر، و`calculateDiscountPercentage` تقرأ
 * `$('#price').val()` بلا فحص فترد `NaN%` قبل كتابة السعر.
 */
(function () {
    'use strict';

    var $ = function (s) { return document.querySelector(s); };

    /* ١ · حقول «الكورس القادم» تتبع اختيار الحالة. */
    var upcoming = $('[data-tqa-upcoming]');
    if (upcoming) {
        var syncStatus = function () {
            var picked = document.querySelector('[data-tqa-status]:checked');
            upcoming.hidden = !picked || picked.value !== 'upcoming';
        };
        Array.prototype.forEach.call(document.querySelectorAll('[data-tqa-status]'), function (r) {
            r.addEventListener('change', syncStatus);
        });
        syncStatus();
    }

    /* ٢ · حقول السعر تختفي مع الكورس المجاني. */
    var free = $('[data-tqa-free]');
    var paid = $('[data-tqa-paid]');
    if (free && paid) {
        var syncFree = function () { paid.hidden = free.checked; };
        free.addEventListener('change', syncFree);
        syncFree();
    }

    /* ٣ · عدد الأشهر يظهر مع «مدة محدودة» وحدها. */
    var months = $('[data-tqa-months]');
    if (months) {
        var syncExpiry = function () {
            var picked = document.querySelector('[data-tqa-expiry]:checked');
            months.hidden = !picked || picked.value !== 'limited_time';
        };
        Array.prototype.forEach.call(document.querySelectorAll('[data-tqa-expiry]'), function (r) {
            r.addEventListener('change', syncExpiry);
        });
        syncExpiry();
    }

    /* ٤ · نسبة الخصم تحسب من السعرين — و«—» حين لا يمكن حسابها،
           لا `NaN%` كما كانت تعرض قبل كتابة السعر الأصلي. */
    var price = $('#price');
    var disc  = $('[data-tqa-discount]');
    var out   = $('[data-tqa-discount-pct]');

    if (price && disc && out) {
        var syncPct = function () {
            var p = parseFloat(price.value);
            var d = parseFloat(disc.value);

            if (!isFinite(p) || p <= 0 || !isFinite(d) || d < 0 || d > p) {
                out.textContent = '—';
                return;
            }
            out.textContent = Math.round((p - d) / p * 100) + '%';
        };

        price.addEventListener('input', syncPct);
        disc.addEventListener('input', syncPct);
        syncPct();
    }
})();
</script>
