<?php
/**
 * النوافذ المشتركة في اللوحة.
 *
 * حذف من هذا الملف ما صار له بديل واحد:
 *
 * ١ — **ثلاث نوافذ تأكيد** (`#alert-modal` · `#ajax-alert-modal` ·
 *     `#data-import-alert-modal`) بأيقونات `dripicons` ونصوص إنجليزية
 *     («Heads up!» · «Are you sure?» · «Continue»). وهي أظهر بقايا
 *     القالب القديم: تفتح فوق شاشة عربية بهوية أخرى وخط آخر وزر أحمر
 *     بحواف غير حواف المنصة.
 *
 *     وصارت `TQA.confirm()` في [assets/taqdar/js/admin.js] — نافذة واحدة
 *     بهوية تقدر، تحبس التركيز، وتغلق بـEscape، وترجع وعدا. والاسمان
 *     `confirm_modal()` و`ajax_confirm_modal()` معرفان هناك بالتوقيع
 *     نفسه، فالمواضع الواحد والعشرون التي تناديهما تعمل بلا أن تمس.
 *
 * ٢ — **`#ai-modal` و`AIModal()`**: تفتحان شاشة «AI Writer» وهي مشروطة
 *     بـ`addon_status('course_ai')` — و`controllers/addons/` مجلد فارغ،
 *     فالشرط كاذب أبدا ولا يفتحها شيء.
 *
 * والباقي يحمل نداءات حقيقية: `showAjaxModal` في أحد عشر موضعا،
 * و`showLargeModal` في ستة، و`showRightModal` في شاشة قوالب الإشعارات.
 *
 * ونص التحميل صار عربيا: كان «loading...» و«...» — والأول إنجليزي
 * والثاني لا يقول شيئا.
 */
?>
<script type="text/javascript">
/** يحمل شاشة في نافذة متوسطة قابلة للتمرير. */
function showAjaxModal(url, header) {
    tqModalLoad('#scrollable-modal', url, header);
}

/** يحمل شاشة في نافذة عريضة. */
function showLargeModal(url, header) {
    tqModalLoad('#large-modal', url, header);
}

/** يحمل شاشة في لوح جانبي. */
function showRightModal(url, header) {
    tqModalLoad('#right-modal', url, header);
}

/**
 * المحرك المشترك للثلاث.
 *
 * كانت كل واحدة تكرر الجسم نفسه بنص تحميل مختلف — وبلا معالج `error`:
 * نداء يفشل يترك دوارة تدور إلى الأبد، فيظن من ينتظر أن الشبكة بطيئة
 * ويعيد فتح النافذة عشر مرات.
 */
function tqModalLoad(sel, url, header) {
    var $m = jQuery(sel);
    $m.find('.modal-title').text(header || 'جار التحميل…');
    $m.find('.modal-body').html(
        '<div class="tqa-modal-load"><span class="spinner-border text-secondary" role="status"></span>' +
        '<span>جار التحميل…</span></div>'
    );
    $m.modal('show', { backdrop: 'true' });

    jQuery.ajax({
        url: url,
        success: function (response) {
            $m.find('.modal-body').html(response);
            $m.find('.modal-title').text(header || '');
        },
        error: function () {
            $m.find('.modal-body').html(
                '<p class="tqa-flash tqa-flash--err" role="alert">' +
                'تعذر تحميل هذه الشاشة. تحقق من اتصالك ثم أعد المحاولة.</p>'
            );
        }
    });
}
</script>

<!-- نافذة عريضة -->
<div class="modal fade" id="large-modal" tabindex="-1" role="dialog" aria-labelledby="largeModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="largeModalTitle"></h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo te('إغلاق'); ?>">&times;</button>
            </div>
            <div class="modal-body"></div>
        </div>
    </div>
</div>

<!-- نافذة قابلة للتمرير -->
<div class="modal fade" id="scrollable-modal" tabindex="-1" role="dialog" aria-labelledby="scrollableModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scrollableModalTitle"></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo te('إغلاق'); ?>">&times;</button>
            </div>
            <div class="modal-body"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo t('إغلاق'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- لوح جانبي -->
<div class="modal fade" id="right-modal" tabindex="-1" role="dialog" aria-labelledby="rightModalTitle" aria-modal="true">
    <div class="modal-dialog modal-lg modal-right">
        <div class="modal-content h-100">
            <div class="modal-header">
                <h4 class="modal-title" id="rightModalTitle"></h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo te('إغلاق'); ?>">&times;</button>
            </div>
            <div class="modal-body"></div>
        </div>
    </div>
</div>

<script>
/* زر الإغلاق يغلق فعلا: بعض النوافذ تحقن بـAJAX بعد تهيئة Bootstrap،
   فلا يربط بها المستمع الأصلي — والمفوض يلتقطها كلها. */
jQuery(document).on('click', '[data-dismiss="modal"]', function () {
    jQuery(this).closest('.modal').modal('hide');
});
</script>
