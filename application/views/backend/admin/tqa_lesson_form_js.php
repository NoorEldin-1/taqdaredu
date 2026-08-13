<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<script>
/**
 * سلوك نموذج الدرس — الإضافة والتحرير.
 *
 * شيئان: قراءة مدة الفيديو من الرابط، ورفع يعرض تقدمه.
 *
 * TQ-UPLOAD-SILENT — كان معالج `error` في `ajaxForm` **فارغا** وفيه
 * تعليق «You can write here your js error message». فرفع يسقط — لانقطاع
 * أو لتجاوز حد حجم الملف في PHP — يترك الزر مكتوبا عليه «جار الرفع…
 * ٩٩٪» **إلى الأبد**، ومعطلا. فلا الدرس حفظ ولا المستخدم علم.
 */
(function () {
    'use strict';

    /* ---- ١ · مدة الفيديو تقرأ من الرابط ---- */
    var url  = document.getElementById('video_url');
    var dur  = document.getElementById('duration');
    var busy = document.getElementById('perloader');
    var bad  = document.getElementById('invalid_url');

    var valid = function (v) {
        return /^(?:https?:\/\/)?(?:www\.)?(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))((\w|-){11})(?:\S+)?$/.test(v)
            || /^(https?:\/\/)?(www\.)?(vimeo\.com\/)([0-9]+)$/.test(v);
    };

    if (url && dur) {
        url.addEventListener('blur', function () {
            var v = url.value.trim();
            if (v === '') return;

            if (!valid(v)) {
                if (bad)  bad.hidden = false;
                if (busy) busy.hidden = true;
                dur.value = '';
                return;
            }

            if (bad)  bad.hidden = true;
            if (busy) busy.hidden = false;

            var body = new URLSearchParams();
            body.set('video_url', v);
            if (window.TQ_CSRF && TQ_CSRF.name) body.set(TQ_CSRF.name, TQ_CSRF.hash);

            fetch(<?php echo json_encode(site_url('admin/ajax_get_video_details')); ?>, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            }).then(function (r) { return r.ok ? r.text() : Promise.reject(r.status); })
              .then(function (t) { dur.value = t.trim(); })
              .catch(function () {
                  /* تعذر قراءة المدة لا يمنع الحفظ — تكتب بيد. */
                  if (window.TQA) TQA.warn('تعذر قراءة مدة الفيديو. اكتبها يدويا.');
              })
              .then(function () { if (busy) busy.hidden = true; });
        });
    }

    /* ---- ٢ · الرفع يعرض تقدمه، وفشله يعلن ---- */
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
