<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<script>
/**
 * الحقول المتكررة — الأسئلة والمتطلبات والمخرجات.
 *
 * TQ-BLANK-ROW — كانت النسخة السابقة تحتفظ لكل قائمة بكتلة
 * `#blank_faq_field` **داخل النموذج**، تخفيها بـjQuery عند التحميل ثم
 * تنسخ منها عند الضغط على «+». وأعطالها أربعة:
 *
 * ١ — **الحقل المخفي يرسل مع النموذج.** `hide()` تخفي بصريا ولا تعطل،
 *     فكل حفظ يضيف سؤالا فارغا ومتطلبا فارغا ومخرجا فارغا إلى الصف.
 * ٢ — **إن تعثر jQuery ظهر الحقل** — وهو نسخة مكررة تربك من يملأ.
 * ٣ — **`id` مكرر في كل نسخة** (`id="faqs"` على كل حقل)، فـ`<label for>`
 *     يشير إلى أولها أبدا.
 * ٤ — **`removeFaq` تحذف `parent().parent()`** — أي تعتمد على عمق دقيق
 *     في الشجرة. أي غلاف يضاف يجعل الزر يحذف نصف النموذج.
 *
 * وهذه النسخة تنسخ **آخر عنصر موجود** وتفرغه، ولا تبقي في الصفحة حقلا
 * لا يقصد إرساله. والحذف يصعد إلى `[data-tqa-rep-item]` بالاسم لا بالعدد.
 */
(function () {
    'use strict';

    Array.prototype.forEach.call(document.querySelectorAll('[data-tqa-rep]'), function (list) {
        var add = document.querySelector('[data-tqa-rep-add="' + list.getAttribute('data-tqa-rep') + '"]');

        var wire = function (item) {
            var btn = item.querySelector('[data-tqa-rep-remove]');
            if (!btn) return;

            btn.addEventListener('click', function () {
                var items = list.querySelectorAll('[data-tqa-rep-item]');

                /* آخر عنصر يفرغ ولا يحذف: القائمة الخالية تترك المستخدم
                   بلا حقل ولا زر يضيف إليه شيئا. */
                if (items.length > 1) {
                    item.parentNode.removeChild(item);
                } else {
                    Array.prototype.forEach.call(item.querySelectorAll('input, textarea'), function (f) {
                        f.value = '';
                    });
                }
            });
        };

        Array.prototype.forEach.call(list.querySelectorAll('[data-tqa-rep-item]'), wire);

        if (!add) return;

        add.addEventListener('click', function () {
            var items = list.querySelectorAll('[data-tqa-rep-item]');
            var copy  = items[items.length - 1].cloneNode(true);

            Array.prototype.forEach.call(copy.querySelectorAll('input, textarea'), function (f) {
                f.value = '';
                f.removeAttribute('id');
            });

            list.appendChild(copy);
            wire(copy);

            var first = copy.querySelector('input, textarea');
            if (first) first.focus();
        });
    });
})();
</script>
