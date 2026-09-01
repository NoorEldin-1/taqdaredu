/**
 * حاجز المعاينة — TQ-PREVIEW-CAP.
 *
 * ═══ لماذا وجد ═══
 *
 * `lesson.is_free` كان يفتح الدرس **كاملا** لمن لم يدفع. فالدرس الذي
 * طوله ثلاث عشرة دقيقة يشاهد كله، وشارة «معاينة مجانية» تعد بمعاينة
 * وتسلم درسا. والصفحة نفسها كانت تكتب «شاهده كاملا قبل أن تشترك» —
 * فالوعد المكتوب كان صادقا والسياسة هي الخطأ.
 *
 * والحد `tq_preview_seconds` في `settings`، يقرأه الخادم ويطبعه في
 * `data-tq-preview-cap`. **لا رقم مكتوب في هذا الملف**: تغيير السياسة
 * صف في القاعدة لا نشر شيفرة.
 *
 * ═══ ولا مشغل ثانيا ═══
 *
 * الإطار يبنيه `tqs_video_embed()` في الخادم كما كان، وهذا الملف
 * **يربط به** `Vimeo.Player` — والمكتبة تقبل إطارا قائما ولا تشترط
 * أن تبنيه. فلا يتغير وسم ولا يتكرر مشغل، ولو عطل هذا السكربت رجعت
 * المعاينة إلى ما كانت عليه بلا صفحة مكسورة.
 *
 * ═══ ولماذا `seeked` كذلك ═══
 *
 * `timeupdate` وحده يمسك من **شاهد** حتى الحد. ومن سحب المؤشر إلى
 * الدقيقة العاشرة مباشرة لا يمر بالحد أصلا — فيفتح ما بعده. والحدان
 * معا: من بلغ الحد وقف، ومن قفز فوقه أعيد إليه.
 *
 * ═══ ويوتيوب خارج هذا ═══
 *
 * يحتاج `enablejsapi=1` في الإطار و IFrame API غيرها. ودروس تقدر
 * الجديدة كلها فيميو، فلم يبن ما لا يستعمل. والدرس اليوتيوبي يعاين
 * كاملا كما كان — لا ينكسر، ولكن لا يحد.
 */
(function () {
    'use strict';

    var SDK = 'https://player.vimeo.com/api/player.js';

    function noop() {}

    /** يحمل المكتبة مرة واحدة ولو نودي مرارا. */
    var pending = null;
    function loadSdk() {
        if (window.Vimeo && window.Vimeo.Player) return Promise.resolve();
        if (pending) return pending;
        pending = new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = SDK;
            s.async = true;
            s.onload = resolve;
            s.onerror = function () { reject(new Error('vimeo sdk')); };
            document.head.appendChild(s);
        });
        return pending;
    }

    function t(ar) {
        return (window.TQ && typeof window.TQ.t === 'function') ? window.TQ.t(ar) : ar;
    }

    function init(box) {
        var cap = parseInt(box.getAttribute('data-tq-preview-cap'), 10);
        if (!cap || cap < 1) return;

        var frame = box.querySelector('iframe');
        if (!frame) return;

        /* فيميو وحدها. والفحص على `src` لا على نوع الدرس: الخادم قد
           يكتب `Vimeo` في العمود ويخرج إطارا آخر، والذي يشغل هو ما
           في الصفحة لا ما في القاعدة. */
        if (!/player\.vimeo\.com/.test(frame.getAttribute('src') || '')) return;

        var player = box.querySelector('[data-tq-preview-player]') || frame.parentNode;
        var wall   = document.querySelector('[data-tq-preview-wall]');

        loadSdk().then(function () {
            var vp = new window.Vimeo.Player(frame);
            var stopped = false;

            function stop() {
                if (stopped) return;
                stopped = true;
                vp.pause().catch(noop);
                if (player) player.hidden = true;
                if (wall) {
                    wall.hidden = false;
                    /* البطاقة تحل محل المشغل في التدفق، فينقل إليها
                        التركيز — ومن يتصفح بلوحة المفاتيح لا يبقى
                        مركزه على إطار صار مخفيا. */
                    var f = wall.querySelector('a, button');
                    if (f && typeof f.focus === 'function') f.focus();
                }
            }

            function resume() {
                stopped = false;
                if (wall)   wall.hidden = true;
                if (player) player.hidden = false;
                vp.setCurrentTime(0).then(function () { return vp.play(); }).catch(noop);
            }

            vp.on('timeupdate', function (d) {
                if (!stopped && d && d.seconds >= cap) stop();
            });

            /* القفز فوق الحد يعاد إليه — ولا يعد إيقافا: من سحب
               المؤشر لم يشاهد بعد. */
            vp.on('seeked', function (d) {
                if (stopped || !d) return;
                if (d.seconds > cap) vp.setCurrentTime(cap).catch(noop);
            });

            if (wall) {
                var again = wall.querySelector('[data-tq-preview-again]');
                if (again) again.addEventListener('click', function (e) {
                    e.preventDefault();
                    resume();
                });
            }
        }).catch(noop);
    }

    function boot() {
        var boxes = document.querySelectorAll('[data-tq-preview-cap]');
        for (var i = 0; i < boxes.length; i++) init(boxes[i]);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
