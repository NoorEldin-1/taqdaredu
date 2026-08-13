<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<script>
/**
 * ترشيح منتقي طويل في المتصفح.
 *
 * بديل عن `select2` — وهو غير محمل في اللوحة (TQ-SELECT2-GONE)، فكانت
 * المنتقيات التي تعتمد عليه تخرج قوائم عارية بمئات البنود بلا بحث.
 * والحقل هنا يخفي ما لا يطابق، ويبقي المحدد ظاهرا دائما.
 */
(function () {
    'use strict';

    Array.prototype.forEach.call(document.querySelectorAll('[data-tqa-filter]'), function (box) {
        var select = document.getElementById(box.getAttribute('data-tqa-filter'));
        if (!select || box.dataset.tqaFilterOn === '1') return;
        box.dataset.tqaFilterOn = '1';

        var all = Array.prototype.slice.call(select.options);

        box.addEventListener('input', function () {
            var q = box.value.trim().toLowerCase();

            all.forEach(function (opt) {
                /* الخيار المحدد يبقى ظاهرا مهما رشح — وإلا اختفى من
                   الشاشة وهو القيمة التي سترسل. والخيار الفارغ الأول
                   يبقى كذلك، فهو باب إلغاء الاختيار. */
                opt.hidden = !(opt.selected || opt.value === ''
                            || q === '' || opt.text.toLowerCase().indexOf(q) !== -1);
            });
        });
    });
})();
</script>
