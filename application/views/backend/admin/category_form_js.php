<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<script>
/**
 * الغلاف يتبع الأب: قسم رئيسي فغلاف عريض، وفرعي فأيقونة صغيرة.
 *
 * والنموذج يحفظ كاملا بلا جافاسكربت إطلاقا — الحقلان كلاهما في الـDOM،
 * والفارغ منهما لا يرسل شيئا. وهذا كان عطل النسخة السابقة: كانت تخفي
 * الاثنين بـ`$(document).ready` ثم تظهر أحدهما، فإن تعثر jQuery بقي
 * الاثنان مخفيين ولم يكن رفع غلاف ممكنا أصلا.
 */
(function () {
    'use strict';

    var sel = document.querySelector('[data-tqa-parent]');
    if (!sel) return;

    var parentBox = document.querySelector('[data-tqa-cover="parent"]');
    var subBox    = document.querySelector('[data-tqa-cover="sub"]');

    var sync = function () {
        var isSub = parseInt(sel.value, 10) > 0;
        if (parentBox) parentBox.hidden = isSub;
        if (subBox)    subBox.hidden    = !isSub;
    };

    sel.addEventListener('change', sync);
    sync();
})();
</script>

<?php include 'tqa_file_js.php'; ?>
