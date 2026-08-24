<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<script>
/**
 * سلوك نموذج الدرس — الإضافة والتحرير.
 *
 * شيء واحد الآن: رفع يعرض تقدمه وفشله يعلن.
 *
 * TQ-UPLOAD-SILENT — كان معالج `error` في `ajaxForm` **فارغا** وفيه
 * تعليق «You can write here your js error message». فرفع يسقط — لانقطاع
 * أو لتجاوز حد حجم الملف في PHP — يترك الزر مكتوبا عليه «جار الرفع…
 * ٩٩٪» **إلى الأبد**، ومعطلا. فلا الدرس حفظ ولا المستخدم علم.
 *
 * ═══ TQ-PROBE — وأين ذهبت قراءة المدة ═══
 *
 * كانت هنا كتلة تنادي `admin/ajax_get_video_details`، وهو ينادي
 * `Video_model::getVideoDetails()` الذي يطلب واجهة يوتيوب بمفتاح
 * `youtube_api_key` من `settings`. **والمفتاح فارغ** (وكذلك مفتاح
 * فيميو)، فيرد الطلب خطأ ويرد المتحكم نصا فارغا — وكان هذا السطر:
 *
 *     .then(function (t) { dur.value = t.trim(); })
 *
 * يكتب الفراغ في الحقل. أي أن «قارئ المدة» كان **يمحو ما كتبه المسؤول
 * بيده** ويسمي ذلك قراءة، ولا رسالة.
 *
 * والقراءة الآن في المتصفح من [tq-duration-probe.js] بالمشغل نفسه الذي
 * يشغل الدرس عند الطالب — بلا مفتاح ولا نداء شبكة من الخادم — ويحمله
 * `_tq_videourl_fields.php` مع الحقلين. وهو المحرك نفسه الذي يخدم شاشة
 * المنهج عند المعلم، فلا رقمان.
 */
(function () {
    'use strict';

    /* ---- الرفع يعرض تقدمه، وفشله يعلن ---- */
    var $ = window.jQuery;
    if (!$ || !$.fn || !$.fn.ajaxForm) return;

    $('.ajaxFormSubmission').each(function () {
        var form = $(this);
        var btn  = form.find('.formSubmissionBtn');
        var idle = btn.html();

        var release = function (msg) {
            btn.prop('disabled', false).html(idle);
            if (msg && window.TQA) TQA.error(msg);
        };

        form.ajaxForm({
            beforeSend: function () {
                btn.prop('disabled', true).text('جار الرفع… 0%');
            },
            uploadProgress: function (e, position, total, percent) {
                btn.text(percent < 100 ? ('جار الرفع… ' + percent + '%') : 'يعالج على الخادم…');
            },
            complete: function (xhr) {
                var res;
                try { res = JSON.parse(xhr.responseText); }
                catch (e) {
                    /* رد ليس JSON يعني خطأ خادم أو تحذير PHP تسرب قبله. */
                    release('تعذر حفظ الدرس. راجع حجم الملف وحدود الرفع.');
                    return;
                }

                if (res && res.error)    { release(res.error); return; }
                if (res && res.success && window.TQA) TQA.ok(res.success);

                if (res && res.redirect) { window.location.href = res.redirect; return; }
                if (res && res.reload)   { window.location.reload(); return; }

                release();
            },
            error: function () {
                release('انقطع الاتصال قبل اكتمال الرفع. لم يحفظ الدرس.');
            }
        });
    });
})();
</script>
