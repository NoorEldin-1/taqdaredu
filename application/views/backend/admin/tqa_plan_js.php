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

/**
 * لوحة «قسمة إيراد هذه الباقة» تتبع السعر والنسبة والصفوف لحظة بلحظة.
 *
 * والقسمة هنا **نسخة حرفية** من `Taqdar_revenue_model::allocate()`:
 * `intdiv` وباقيه الصحيح، ثم توزع الهللات المتبقية على أصحاب أكبر
 * البواقي. ولو اختلفت خوارزمية المتصفح عن خوارزمية الخادم بهللة واحدة
 * لصار ما وعد به المسؤول غير ما قيد — وهي الهللة التي تكتشف في مراجعة
 * لا في شاشة.
 *
 * ولا `round()` على الحصص: المال هللات صحيحة هنا كما هو في الدفتر،
 * والقسمة على مئة حد عرض أخير لا حساب.
 */
(function () {
    'use strict';

    var panel = document.querySelector('[data-tqa-split]');
    if (!panel) return;

    var map, names;
    try {
        map   = JSON.parse(panel.getAttribute('data-tqa-split-map')   || '{}');
        names = JSON.parse(panel.getAttribute('data-tqa-split-names') || '{}');
    } catch (e) { return; }

    var form    = document.querySelector('[data-tqa-form]');
    var priceEl = form && form.querySelector('[name="price"]');
    var pctEl   = form && form.querySelector('[name="teacher_pool_percent"]');
    var scopeEl = document.querySelector('[data-tqa-field="scope"]');
    var picks   = document.querySelector('[data-tqa-picks="scope_ids"]');

    var dflt    = parseFloat(panel.getAttribute('data-tqa-split-default') || '15');
    var body    = panel.querySelector('[data-tqa-split-body]');
    var filled  = panel.querySelector('[data-tqa-split-filled]');
    var empty   = panel.querySelector('[data-tqa-split-empty]');
    var emptyWhy= panel.querySelector('[data-tqa-split-empty-why]');
    var basis   = panel.querySelector('[data-tqa-split-basis]');
    var noprice = panel.querySelector('[data-tqa-split-noprice]');

    /**
     * هللات ⇐ نص ريالات. الخانتان تظهران حين يكون فيه هللات، فلا يدور مال.
     * والفواصل على الصحيح وحده: `1,234.5,6` ليست مبلغا.
     */
    function money(h) {
        h = Math.round(h);
        var neg   = h < 0;
        var abs   = Math.abs(h);
        var whole = String(Math.floor(abs / 100)).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        var frac  = abs % 100;
        return (neg ? '−' : '') + whole
             + (frac === 0 ? '' : '.' + (frac < 10 ? '0' + frac : String(frac)))
             + ' ر.س';
    }

    function put(key, text) {
        Array.prototype.forEach.call(
            panel.querySelectorAll('[data-tqa-split-n="' + key + '"]'),
            function (el) { el.textContent = text; }
        );
    }

    /** أكبر البواقي — حرفا بحرف كما في الخادم. */
    function allocate(amount, weights) {
        var keys = Object.keys(weights), out = {}, rem = [], total = 0, sum = 0, i;
        keys.forEach(function (k) { total += weights[k]; out[k] = 0; });
        if (amount <= 0 || total <= 0) return out;

        keys.forEach(function (k) {
            var num = amount * weights[k];
            out[k]  = Math.floor(num / total);
            rem.push({ k: k, r: num % total });
            sum    += out[k];
        });

        var left = amount - sum;
        /* الترتيب بالباقي نازلا، والتعادل يكسر بترتيب الدخول — وهو ترتيب
           `contributors()` الثابت، فلا تنتقل الهللة الأخيرة بين معلمين
           بين رسمين. */
        rem.sort(function (a, b) { return b.r - a.r; });
        for (i = 0; i < rem.length && left > 0; i++) { out[rem[i].k]++; left--; }
        return out;
    }

    function selectedGrades() {
        var ids = [];
        if (!picks) return ids;
        Array.prototype.forEach.call(
            picks.querySelectorAll('.tqa-pick input[type="checkbox"]'),
            function (b) { if (b.checked) ids.push(parseInt(b.value, 10)); }
        );
        return ids;
    }

    function recalc() {
        var scope = scopeEl ? String(scopeEl.value || 'grade') : 'grade';
        var gross = priceEl
                  ? Math.round((parseFloat(String(priceEl.value).replace(/,/g, '')) || 0) * 100)
                  : 0;

        var raw = pctEl ? String(pctEl.value || '').trim() : '';
        var isDefault = (raw === '' || isNaN(parseFloat(raw)));
        var pct = isDefault ? dflt : Math.max(0, Math.min(100, parseFloat(raw)));

        var pool     = Math.round(gross * pct / 100);
        var platform = gross - pool;

        if (basis)   basis.hidden   = !isDefault;
        if (noprice) noprice.hidden = gross > 0;

        put('platform_money', money(platform));
        put('pool_money',     money(pool));
        put('pool_money2',    money(pool));
        put('platform_pct',   String(Math.round((100 - pct) * 100) / 100));
        put('pool_pct',       String(Math.round(pct * 100) / 100));

        var segP = panel.querySelector('[data-tqa-split-seg="platform"]');
        var segT = panel.querySelector('[data-tqa-split-seg="pool"]');
        if (segP) segP.style.flex = String(Math.max(1, 100 - pct));
        if (segT) segT.style.flex = String(Math.max(1, pct));

        /* الخريطة مفهرسة بالصف، فلا تصلح إلا لنطاق يقرأ الصفوف. وباقة
           المادة أو المسار مستحقوها من مساراتها هي — يحسبهم الخادم في
           `plan_contributors()`، ولا يعيد المتصفح حسابهم من خريطة لا
           تعرفهم فيمحو جدولا صحيحا ويكتب «لا معلم يستحق». والشطران
           أعلاه محدثان على كل حال: هما من السعر والنسبة لا من النطاق. */
        if (scope !== 'grade' && scope !== 'all') return;

        /* الدورة تعد مرة واحدة ولو ظهرت في صفين — القاعدة نفسها التي في
           `contributors()`، وبلاها يتضخم وزن صاحبها بلا أن ينشر درسا. */
        var gids = (scope === 'all') ? Object.keys(map) : selectedGrades();
        var seen = {}, agg = {}, lessonsTotal = 0;

        gids.forEach(function (g) {
            (map[g] || []).forEach(function (e) {
                if (seen[e.c]) return;
                seen[e.c] = 1;
                if (!agg[e.t]) agg[e.t] = { lessons: 0, weight: 0 };
                agg[e.t].lessons += e.n;
                agg[e.t].weight  += e.n * e.f;
                lessonsTotal     += e.n;
            });
        });

        var tids = Object.keys(agg);
        if (!tids.length) {
            if (filled) filled.hidden = true;
            if (empty)  empty.hidden  = false;
            if (emptyWhy) {
                emptyWhy.textContent = (scope === 'grade' && !gids.length)
                    ? 'اختر صفوف الباقة أولا — القسمة تقرأ مسارات هذه الصفوف.'
                    : 'لا مسار منشور بمعلم مسند في نطاق هذه الباقة. فلو بيعت اليوم لبقي وعاء '
                      + money(pool) + ' بلا صاحب، واحتفظت به المنصة بلا قرار.';
            }
            return;
        }
        if (filled) filled.hidden = false;
        if (empty)  empty.hidden  = true;

        /* الأثقل أولا ثم بالمعرف — ترتيب `contributors()` نفسه، وعليه
           تقوم قسمة البواقي. */
        tids.sort(function (a, b) {
            if (agg[b].weight !== agg[a].weight) return agg[b].weight - agg[a].weight;
            return parseInt(a, 10) - parseInt(b, 10);
        });

        var weights = {}, wTotal = 0;
        tids.forEach(function (t) { weights[t] = agg[t].weight; wTotal += agg[t].weight; });
        var shares = allocate(pool, weights);

        var html = '';
        tids.forEach(function (t) {
            var pctW = wTotal > 0 ? Math.round(agg[t].weight * 10000 / wTotal) / 100 : 0;
            html += '<tr data-tqa-split-row="' + t + '">'
                  + '<td><b></b></td>'
                  + '<td>' + agg[t].lessons + ' <span class="tqa-dim">من ' + lessonsTotal + '</span></td>'
                  + '<td>' + pctW + '%</td>'
                  + '<td><b>' + money(shares[t]) + '</b></td>'
                  + '</tr>';
        });
        if (body) {
            body.innerHTML = html;
            /* الاسم يكتب نصا لا وسما: أسماء المعلمين مدخلات مستخدمين،
               وحقنها في `innerHTML` تجعل اسما فيه وسم يشغل سكربتا في
               لوحة الإدارة. */
            tids.forEach(function (t) {
                var cell = body.querySelector('[data-tqa-split-row="' + t + '"] b');
                if (cell) cell.textContent = names[t] || ('#' + t);
            });
        }
        put('lessons_total', lessonsTotal + ' درسا');
    }

    document.addEventListener('tqa:picks', recalc);
    if (scopeEl) scopeEl.addEventListener('change', recalc);
    if (priceEl) priceEl.addEventListener('input',  recalc);
    if (pctEl)   pctEl.addEventListener('input',    recalc);
    recalc();
})();
</script>
