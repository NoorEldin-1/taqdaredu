<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<script>
/**
 * حقل الوسوم — بلا مكتبة.
 *
 * TQ-TAGSINPUT-CDN — كانت اللوحة تجلب `bootstrap-tagsinput` من
 * `cdnjs.cloudflare.com`، وتستعمله في «المهارات» و«كلمات الموقع»
 * و«الكلمات الدلالية». وثلاثة أعطال في ذلك:
 *
 * ١ — **يحتاج شبكة إلى مضيف ثالث.** الملف يحمل من نطاق آخر في كل صفحة،
 *     فإن حجب أو تأخر بقي الحقل نصا خاما فيه «تقدر,taqdar,تعليم» — وهو
 *     ما يراه المستخدم اليوم في نصف الأحيان.
 * ٢ — **حباته حمراء بعلامة ×.** الأحمر في هذه الهوية لون خطأ لا لون
 *     قيمة عادية، فكل كلمة مفتاحية تقرأ تحذيرا.
 * ٣ — **يكتب فوق الحقل الأصلي.** فقيمة النموذج تعتمد على المكتبة، وإن
 *     لم تحمل أرسل الحقل ما فيه — أو لم يرسل شيئا.
 *
 * وهذه النسخة تعكس القاعدة: **القيمة الحقيقية في `<input type="hidden">`
 * دائما**، والحبات مرآة له لا مصدرا. فإن لم يعمل هذا السطر أرسل النموذج
 * القيمة المحفوظة كما هي — التعديل وحده يضيع، لا البيانات.
 */
(function () {
    'use strict';

    Array.prototype.forEach.call(document.querySelectorAll('[data-tqa-tags]'), function (box) {
        var store = box.querySelector('[data-tqa-tags-value]');
        var input = box.querySelector('[data-tqa-tags-input]');
        if (!store || !input) return;

        /* هذا الملف يضمن في الصفحة وفي النوافذ المجلوبة بـAJAX معا،
           وjQuery تنفذ `<script>` المحقون. فبلا هذا الحارس يربط الحقل
           مرتين، فترسم كل حبة نسختين وتحذف الضغطة الواحدة واحدة منهما. */
        if (box.dataset.tqaTagsOn === '1') return;
        box.dataset.tqaTagsOn = '1';

        var tags = String(store.value || '')
            .split(',')
            .map(function (t) { return t.trim(); })
            .filter(function (t) { return t !== ''; });

        var commit = function () {
            store.value = tags.join(',');
        };

        var render = function () {
            /* الحبات وحدها تمسح — الحقل المخفي وحقل الإدخال يبقيان. */
            Array.prototype.forEach.call(box.querySelectorAll('.tqa-tags__tag'), function (el) {
                el.parentNode.removeChild(el);
            });

            tags.forEach(function (tag, i) {
                var chip = document.createElement('span');
                chip.className = 'tqa-tags__tag';

                var text = document.createElement('span');
                text.textContent = tag;
                chip.appendChild(text);

                var x = document.createElement('button');
                x.type = 'button';
                x.className = 'tqa-tags__x';
                x.setAttribute('aria-label', 'احذف ' + tag);
                x.innerHTML = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none"'
                            + ' stroke="currentColor" stroke-width="3" stroke-linecap="round"'
                            + ' aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>';
                x.addEventListener('click', function () {
                    tags.splice(i, 1);
                    commit();
                    render();
                    input.focus();
                });
                chip.appendChild(x);

                box.insertBefore(chip, input);
            });
        };

        var add = function (raw) {
            /* اللصق قد يأتي بقائمة كاملة مفصولة بفواصل — تقبل كما هي. */
            String(raw).split(',').forEach(function (t) {
                t = t.trim();
                if (t !== '' && tags.indexOf(t) === -1) tags.push(t);
            });
            input.value = '';
            commit();
            render();
        };

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ',') {
                /* Enter داخل حقل الوسم يضيف وسما ولا يرسل النموذج. */
                e.preventDefault();
                add(input.value);
                return;
            }
            /* Backspace على حقل فارغ يحذف آخر وسم — ما يتوقعه من اعتاد
               هذه الحقول في غير هذا النظام. */
            if (e.key === 'Backspace' && input.value === '' && tags.length) {
                tags.pop();
                commit();
                render();
            }
        });

        /* مغادرة الحقل تثبت ما فيه: من كتب وسما ثم ضغط «احفظ» مباشرة
           كان يفقده — Enter وحده كان يضيف. */
        input.addEventListener('blur', function () {
            if (input.value.trim() !== '') add(input.value);
        });

        render();
    });
})();
</script>
