<!-- bundle -->
<script src="<?php echo base_url('assets/backend/js/app.min.js'); ?>"></script>
<!-- third party js -->
<script src="<?php echo base_url('assets/backend/js/vendor/Chart.bundle.min.js'); ?>"></script>
<script src="<?php echo base_url('assets/backend/js/vendor/jquery-jvectormap-1.2.2.min.js'); ?>"></script>
<script src="<?php echo base_url('assets/backend/js/vendor/jquery-jvectormap-world-mill-en.js'); ?>"></script>
<script src="<?php echo base_url('assets/backend/js/vendor/jquery.dataTables.min.js'); ?>"></script>
<script src="<?php echo base_url('assets/backend/js/vendor/dataTables.bootstrap4.js'); ?>"></script>
<script src="<?php echo base_url('assets/backend/js/vendor/dataTables.responsive.min.js'); ?>"></script>
<script src="<?php echo base_url('assets/backend/js/vendor/responsive.bootstrap4.min.js'); ?>"></script>
<script src="<?php echo base_url('assets/backend/js/vendor/dataTables.buttons.min.js'); ?>"></script>
<script src="<?php echo base_url('assets/backend/js/vendor/buttons.bootstrap4.min.js'); ?>"></script>
<script src="<?php echo base_url('assets/backend/js/vendor/buttons.html5.min.js'); ?>"></script>
<script src="<?php echo base_url('assets/backend/js/vendor/buttons.flash.min.js'); ?>"></script>
<script src="<?php echo base_url('assets/backend/js/vendor/buttons.print.min.js'); ?>"></script>
<script src="<?php echo base_url('assets/backend/js/vendor/dataTables.keyTable.min.js'); ?>"></script>
<script src="<?php echo base_url('assets/backend/js/vendor/dataTables.select.min.js'); ?>"></script>
<script src="<?php echo base_url('assets/backend/js/vendor/summernote-bs4.min.js'); ?>"></script>
<script src="<?php echo base_url('assets/backend/js/vendor/fullcalendar.min.js'); ?>"></script>
<script src="<?php echo base_url('assets/backend/js/pages/demo.summernote.js'); ?>"></script>
<script src="<?php echo base_url('assets/backend/js/vendor/dropzone.js'); ?>"></script>
<script src="<?php echo base_url('assets/backend/js/pages/datatable-initializer.js'); ?>"></script>
<script src="<?php echo base_url('assets/backend/js/font-awesome-icon-picker/fontawesome-iconpicker.min.js'); ?>" charset="utf-8"></script>
<?php /* TQ-TAGSINPUT-CDN — حذف من هنا نسختان من `bootstrap-tagsinput.min.js`
         (واحدة في `js/vendor/` وأخرى في `js/`، تحملان معا في كل صفحة
         فتكتب الثانية فوق الأولى). لم يبق في اللوحة مستعمل واحد
         لـ`data-role="tagsinput"`: حقول الوسوم تبنى في
         [admin/tqa_tags_js.php] بلا مكتبة. */ ?>
<script src="<?php echo base_url() . 'assets/frontend/default-new/js/bootstrap.bundle.min.js'; ?>"></script>
<script src="<?php echo base_url('assets/backend/js/vendor/dropzone.min.js'); ?>" charset="utf-8"></script>
<script src="<?php echo base_url('assets/backend/js/ui/component.fileupload.js'); ?>" charset="utf-8"></script>
<script src="<?php echo base_url('assets/backend/js/pages/demo.form-wizard.js'); ?>"></script>
<!-- dragula js-->
<script src="<?php echo base_url('assets/backend/js/vendor/dragula.min.js'); ?>"></script>
<!-- component js -->
<script src="<?php echo base_url('assets/backend/js/ui/component.dragula.js'); ?>"></script>

<!-- Jquery form -->
<script src="<?php echo base_url('assets/global/jquery-form/jquery.form.min.js'); ?>"></script>

<script src="<?php echo site_url('assets/backend/js/custom.js'); ?>"></script>

<!-- Dashboard chart's data is coming from this file -->

<?php if (isset($page_name) && $page_name == 'dashboard'): ?>
  <?php include "$logged_in_user_role/dashboard-chart.php"; ?>
<?php endif; ?>

<script type="text/javascript">
  $(document).ready(function() {
    $(function() {
      $('.icon-picker').iconpicker();
    });
  });
</script>

<?php
/**
 * الرسائل الطائرة.
 *
 * كانت هنا كتلتان: أربع دوال `notify()` تنادي `$.NotificationApp.send`
 * بصندوق قالب Hyper، وثلاث كتل تعرض `flashdata` مباشرة عند التحميل.
 *
 * والثانية كانت **تكرارا صريحا**: `backend/index.php` يرسم المفاتيح
 * الثلاثة نفسها (`error_message` · `flash_message` · `info_message`)
 * كـ`tqa-flash` في أعلى الصفحة. فرسالة الحفظ الواحدة تظهر مرتين —
 * شريطا بهوية تقدر وصندوقا أخضر بهوية القالب القديم فوقه. حذفت الكتلة
 * هنا وبقيت المرسومة في الصفحة: هي التي يقرؤها قارئ الشاشة بدور صحيح،
 * وهي التي تبقى بعد أن تختفي الطائرة.
 *
 * وكانت الرسالة تطبع في سلسلة جافاسكربت بلا تهريب: نص فيه فاصلة عليا
 * — وهو وارد في «لا يمكن حذفه» ونحوها — يكسر السطر فيسقط باقي الصفحة.
 *
 * والدوال الأربع بقيت بأسمائها: ينادى `error_notify()` و
 * `error_required_field()` من عشرات الشاشات الموروثة ومن
 * `distributeServerResponse` في `common_scripts.php`. وهي الآن تمر
 * بـ`TQA.toast` (انظر `assets/taqdar/js/admin.js`).
 */
?>
<script type="text/javascript">
  function tq_notify(message, tone) {
    if (window.TQA && TQA.toast) return TQA.toast(message, tone);
    /* admin.js لم يصل بعد أو تعثر تحميله — لا يبتلع الخبر بصمت */
    if (tone === 'err') window.alert(message);
  }
  function notify(message)         { tq_notify(message, 'info'); }
  function success_notify(message) { tq_notify(message, 'ok'); }
  function error_notify(message)   { tq_notify(message, 'err'); }
  function error_required_field()  { tq_notify(<?php echo json_encode(get_phrase('please_fill_all_the_required_fields'), JSON_UNESCAPED_UNICODE); ?>, 'err'); }
</script>

<?php
/**
 * محرر النص الغني — نداء واحد لكل الشاشات.
 *
 * كانت كل شاشة تنادي `initSummerNote(['#about_us', '#terms_and_condition', …])`
 * بقائمة معرفات مكتوبة بيدها، وأكثرها منسوخ من شاشة أخرى: صفحة إعدادات
 * الإشعارات تنادي المحرر على خمسة حقول **لا وجود لأي منها فيها**. والحقل
 * الذي ينسى لا يحمل محررا ولا يشكو أحد.
 *
 * فصارت السمة هي الإعلان: `data-tqa-rich` على أي `<textarea>`. والنداء
 * هنا مرة واحدة لكل الصفحة، ومرة أخرى بعد كل نافذة تجلب محتواها
 * بـAJAX — وإلا خرجت حقول النوافذ بلا محرر (وهو ما كان يحدث في «إضافة
 * درس نصي»: النداء داخل النافذة يسبق حقنها في الصفحة أحيانا).
 */
?>
<script type="text/javascript">
(function () {
    'use strict';

    var $ = window.jQuery;
    if (!$ || !$.fn || !$.fn.summernote) return;

    var TOOLS = [
        ['style', ['bold', 'italic', 'underline', 'clear']],
        ['para',  ['ul', 'ol', 'paragraph']],
        ['insert', ['link']],
        ['view',  ['codeview']]
    ];

    window.tqaRich = function (root) {
        $(root || document).find('textarea[data-tqa-rich]').each(function () {
            /* المحرر ينشأ مرة: النداء الثاني على العنصر نفسه يترك
               محررين فوق بعضهما ويفقد ربط الحقل الأصلي. */
            if (this.dataset.tqaRichOn === '1') return;
            this.dataset.tqaRichOn = '1';

            $(this).summernote({
                height: 220,
                dialogsInBody: true,
                toolbar: TOOLS,
                placeholder: this.getAttribute('placeholder') || ''
            });
        });
    };

    $(function () { window.tqaRich(document); });

    /* النوافذ المجلوبة بـAJAX تحقن بعد التحميل. */
    $(document).on('shown.bs.modal', function (e) { window.tqaRich(e.target); });
})();
</script>