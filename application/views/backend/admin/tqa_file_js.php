<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<script>
/**
 * اسم الملف المختار.
 *
 * `<input type="file">` مخفي بصريا في هيكل `.tqa-file` — لأن مظهره
 * الأصلي زر نظام بخطه ونصه الإنجليزي مهما كانت لغة الصفحة. والملصق
 * يقوم مقامه، لكنه لا يعرف ما اختير. فبلا هذه الأسطر يضغط المستخدم
 * «اختر صورة» ويختار ملفا ولا يرى أثرا لاختياره، فيظن أنه لم ينجح.
 *
 * تحميل الصفحة يمر مرة واحدة على كل حقول الملفات فيها؛ ولا شيء هنا
 * شرط للحفظ — النموذج يرسل الملف بلا هذا السطر.
 */
(function () {
    'use strict';

    Array.prototype.forEach.call(document.querySelectorAll('[data-tqa-file]'), function (input) {
        var box = input.closest('.tqa-file');
        var out = box && box.querySelector('[data-tqa-file-name]');
        if (!out) return;

        /* يضمن في الصفحة وفي النوافذ المجلوبة معا — والحارس يمنع ربطا
           ثانيا يعيد كتابة الاسم مرتين. */
        if (input.dataset.tqaFileOn === '1') return;
        input.dataset.tqaFileOn = '1';

        var idle = out.textContent;

        input.addEventListener('change', function () {
            out.textContent = input.files && input.files.length ? input.files[0].name : idle;
        });
    });
})();
</script>
