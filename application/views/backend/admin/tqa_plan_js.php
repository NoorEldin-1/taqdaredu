<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<script>
/**
 * لوحة «ما تفتحه هذه الباقة» تتبع الصفوف المحددة لحظة بلحظة.
 *
 * والأرقام تحسب في المتصفح من خريطة أرسلها الخادم — لا رحلة شبكة عند كل
 * ضغطة مربع. والخريطة نفسها هي التي حسبت المعروض عند الرسم، فما يراه
 * المسؤول وهو يختار هو نفسه ما سيحفظ.
 *
 * والمواد والمعلمون **يوحدان لا يجمعان**: مادة الرياضيات تدرس في ستة
 * صفوف، وجمع أعداد الصفوف يعدها ستا فيقرأ ولي الأمر وعدا بست مواد وهي
 * مادة. ولهذا ترسل قوائم معرفات لا أرقاما.
 *
 * وبلا هذا الملف تبقى اللوحة على أرقام آخر حفظ — صحيحة، غير حية.
 */
(function () {
    'use strict';

    var panel = document.querySelector('[data-tqa-reach]');
    var picks = document.querySelector('[data-tqa-picks="scope_ids"]');
    if (!panel || !picks) return;

    var map;
    try { map = JSON.parse(panel.getAttribute('data-tqa-reach-map') || '{}'); }
    catch (e) { return; }

    var scope = document.querySelector('[data-tqa-field="scope"]');
    var rows  = panel.querySelector('[data-tqa-reach-rows]');
    var note  = panel.querySelector('[data-tqa-reach-note]');

    function put(key, n) {
        var el = panel.querySelector('[data-tqa-reach-n="' + key + '"]');
        if (el) el.textContent = String(n);
    }

    function recount() {
        var isGrade = !scope || scope.value === 'grade';

        /* نطاق ليس صفوفا: اللوحة لا تعرف ما يفتحه بلا حفظ — فتقول ذلك
           بدل أن تعرض أرقام آخر اختيار وهي لا تخصه. */
        if (!isGrade) {
            if (rows) rows.hidden = true;
            if (note) {
                note.hidden = false;
                note.textContent = 'هذا النطاق لا يحسب من الصفوف. والأرقام تظهر بعد الحفظ.';
            }
            return;
        }
        if (note) note.hidden = true;

        var ids = [];
        Array.prototype.forEach.call(
            picks.querySelectorAll('.tqa-pick input[type="checkbox"]'),
            function (b) { if (b.checked) ids.push(parseInt(b.value, 10)); }
        );

        var t = { grades: ids.length, paths: 0, lessons: 0, quizzes: 0, free: 0 };
        var subjects = {}, teachers = {};

        ids.forEach(function (id) {
            var g = map[id];
            if (!g) return;
            t.paths   += g.paths   || 0;
            t.lessons += g.lessons || 0;
            t.quizzes += g.quizzes || 0;
            t.free    += g.free    || 0;
            (g.subjects || []).forEach(function (s) { subjects[s] = 1; });
            (g.teachers || []).forEach(function (x) { teachers[x] = 1; });
        });

        put('grades',   t.grades);
        put('subjects', Object.keys(subjects).length);
        put('paths',    t.paths);
        put('lessons',  t.lessons);
        put('quizzes',  t.quizzes);
        put('free',     t.free);
        put('teachers', Object.keys(teachers).length);

        /* التفصيل يعرض الصفوف المحددة وحدها — قائمة بكل صفوف المنصة
           تحت باقة من ثلاثة صفوف تخفي ما تعنيه بين ما لا يعنيه. */
        if (rows) {
            rows.hidden = (ids.length === 0);
            Array.prototype.forEach.call(rows.querySelectorAll('[data-tqa-reach-row]'), function (r) {
                r.hidden = ids.indexOf(parseInt(r.getAttribute('data-tqa-reach-row'), 10)) === -1;
            });
        }
    }

    document.addEventListener('tqa:picks', recount);
    if (scope) scope.addEventListener('change', recount);
    recount();
})();
</script>
