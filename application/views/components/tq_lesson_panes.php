<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * تبديل ألواح أنواع الدرس — TQ-UPLOAD-FOLD.
 *
 * الأنواع العشرة تطبع كلها في الصفحة (`data-tqc-pane`) ويعرض المختار
 * وحده. والتبديل بجلب من الخادم كان يفقد ما كتب في الحقول المشتركة
 * ويعطل الشاشة إن تعثر النداء.
 *
 * وهذا الملف مشترك بين شاشتين: مقرر الكورس عند المعلم، وشاشة «رفع
 * الدروس». وكان منطقه مكتوبا في الأولى وحدها — والثانية لا تعرف
 * الأنواع أصلا لأنها كانت تخاطب كاتب دروس ثانيا بنوعين. فلما وحد
 * الكاتب وجب أن يوحد سلوك شاشتيه، وإلا افترقتا عند أول تعديل.
 *
 * وشيئان يفعلهما، وترك أيهما يعطل النموذج بصمت:
 *
 * ١ — **`required` يرفع عن المخفي.** المتصفح يرفض إرسال نموذج فيه حقل
 *     مطلوب مخفي **ولا يقول أين**: الرسالة تشير إلى عنصر غير مرئي،
 *     فيضغط المعلم «احفظ» ولا يحدث شيء ولا يفهم لماذا.
 * ٢ — **المخفي يعطل فلا يرسل.** حقول عشرة أنواع في طلب واحد تجعل
 *     الخادم يقرأ رابط نوع لم يختر — و`collect_fields()` تكتبه.
 */
?>
<script>
(function () {
    'use strict';

    function syncPanes(kind) {
        document.querySelectorAll('[data-tqc-pane]').forEach(function (p) {
            var on = p.getAttribute('data-tqc-pane') === kind;
            p.hidden = !on;
            p.querySelectorAll('input, textarea, select').forEach(function (el) {
                if (on) {
                    if (el.dataset.tqcReq === '1') el.required = true;
                    el.disabled = false;
                } else {
                    if (el.required) el.dataset.tqcReq = '1';
                    el.required = false;
                    el.disabled = true;
                }
            });
        });
    }

    document.querySelectorAll('[data-tq-kind]').forEach(function (r) {
        r.addEventListener('change', function () {
            if (!r.checked) return;
            document.querySelectorAll('.tqc-pick__one').forEach(function (l) {
                l.classList.remove('is-on');
            });
            var label = r.closest('.tqc-pick__one');
            if (label) label.classList.add('is-on');
            syncPanes(r.value);
        });
    });

    var checked = document.querySelector('[data-tq-kind]:checked');
    if (checked) syncPanes(checked.value);

    /* ---- زر الحفظ يحمل نيته ----
       ثلاثة أزرار ترسل النموذج نفسه بثلاث حالات. و`<button value>` لا
       يصل في كل المتصفحات، فالنية تكتب في حقل مخفي صراحة قبل الإرسال. */
    document.querySelectorAll('[data-tqc-submit]').forEach(function (b) {
        b.addEventListener('click', function () {
            var f = b.closest('form');
            var a = f && f.querySelector('[data-tqc-action]');
            if (a) a.value = b.getAttribute('data-tqc-submit');
        });
    });
})();
</script>
