<link rel="shortcut icon" href="<?php echo base_url('uploads/system/').get_frontend_settings('favicon');?>">
<!-- third party css -->
<link href="<?php echo base_url('assets/backend/css/vendor/jquery-jvectormap-1.2.2.css');?>" rel="stylesheet" type="text/css" />
<link href="<?php echo base_url('assets/backend/css/vendor/dataTables.bootstrap4.css');?>" rel="stylesheet" type="text/css" />
<link href="<?php echo base_url('assets/backend/css/vendor/responsive.bootstrap4.css');?>" rel="stylesheet" type="text/css" />
<link href="<?php echo base_url('assets/backend/css/vendor/buttons.bootstrap4.css');?>" rel="stylesheet" type="text/css" />
<link href="<?php echo base_url('assets/backend/css/vendor/select.bootstrap4.css');?>" rel="stylesheet" type="text/css" />
<link href="<?php echo base_url('assets/backend/css/vendor/summernote-bs4.css') ?>" rel="stylesheet" type="text/css" />
<link href="<?php echo base_url('assets/backend/css/vendor/fullcalendar.min.css'); ?>" rel="stylesheet" type="text/css" />
<link rel="stylesheet" href="<?php echo base_url() . 'assets/frontend/default-new/css/bootstrap.min.css'; ?>">
<link href="<?php echo base_url('assets/backend/css/vendor/dropzone.css'); ?>" rel="stylesheet" type="text/css" />
<!-- third party css end -->
<!-- App css -->
<link href="<?php echo base_url('assets/backend/css/app.min.css') ?>" rel="stylesheet" type="text/css" />
<link href="<?php echo base_url('assets/backend/css/icons.min.css'); ?>" rel="stylesheet" type="text/css" />

<link href="<?php echo base_url('assets/backend/css/main.css') ?>" rel="stylesheet" type="text/css" />

<!-- font awesome 5 -->
<link href="<?php echo base_url('assets/backend/css/fontawesome-all.min.css') ?>" rel="stylesheet" type="text/css" />
<link href="<?php echo base_url('assets/backend/css/font-awesome-icon-picker/fontawesome-iconpicker.min.css') ?>" rel="stylesheet" type="text/css" />

<?php /* TQ-TAGSINPUT-CDN — حذف من هنا:
         <link href="https://cdnjs.cloudflare.com/.../bootstrap-tagsinput.css">
         طلب إلى مضيف ثالث في **كل صفحة من صفحات اللوحة**، لورقة أنماط
         مكتبة لم يعد يستعملها أحد: حقول الوسوم كلها تبنى الآن في
         [admin/tqa_tags_js.php] بلا مكتبة. وحذفه يوفر رحلة شبكة
         حاجبة للرسم، ويسقط اعتمادا خارجيا على شبكة قد تحجب. */ ?>

<script src="<?php echo base_url('assets/backend/js/jquery-3.3.1.min.js'); ?>" charset="utf-8"></script>
<script src="<?php echo site_url('assets/backend/js/onDomChange.js');?>"></script>

<?php /* هوية تقدر: التوكنات والخطوط ثم طبقة اللوحة — تحمل بعد CSS القالب فتغلبه. */ ?>
<link rel="preload" as="font" type="font/woff2" crossorigin href="<?php echo tq_asset('site/fonts/Cairo-700-arabic.woff2'); ?>">
<link rel="preload" as="font" type="font/woff2" crossorigin href="<?php echo tq_asset('site/fonts/Plex-400-arabic.woff2'); ?>">
<link rel="stylesheet" href="<?php echo tq_asset('css/fonts.css'); ?>">
<link rel="stylesheet" href="<?php echo tq_asset('css/tokens.css'); ?>">
<?php /* TQA-CONTROLS — المنتق وحقل الملف، وهما مشتركان مع البوابات
         (`css/tqa-controls.css`). قبل `admin.css` فتبقى §١١٫٨ فيها —
         وهي تخص `input-group` من Bootstrap — قادرة على تجاوزها. */ ?>
<link rel="stylesheet" href="<?php echo tq_asset('css/tqa-controls.css'); ?>">
<link rel="stylesheet" href="<?php echo tq_asset('css/admin.css'); ?>">
<?php /* TQ-DS — طبقة الـDesign System. تحمل **بعد** الرموز والهوية فتتجاوزهما،
         وكل قواعدها مقيدة بـ`body.tqa` فلا تمس صفحة عامة. وحذف هذا السطر
         وحده يلغي الـDesign System كله ويرجع اللوحة كما كانت. */ ?>
<link rel="stylesheet" href="<?php echo tq_asset('css/ds.css'); ?>">

<?php /* سلوك اللوحة المشترك — الرسائل الطائرة ونافذة التأكيد.
         يحمل هنا لا في الذيل: يعرف `confirm_modal()` و`ajax_confirm_modal()`
         فوق نسختي القالب القديم، وسمات `data-tqa-confirm` قد ترد في أول
         شاشة قبل أن يصل الذيل. و`defer` يبقيه غير حاجب للرسم. */ ?>
<?php /* TQ-I18N — قاموس المتصفح قبل `admin.js`: نافذة التأكيد ورسائل
         الحفظ كلها تنادي `TQA.t()`. */ ?>
<?php /* `tq-i18n.js` انتقل إلى `index.php` بلا تأجيل: المؤجل ينفذ بعد
         كل سكربت سطري في الشاشات، وهي تنادي `TQA.t()` وقت التحليل. */ ?>
<script src="<?php echo tq_asset('js/admin.js'); ?>" defer></script>

<?php /* المنتق وحقل الملف — مكونان يعمان اللوحة بلا أن تكتب شاشة سطرا:
         كلاهما يمسح الصفحة عند التحميل ثم يراقب ما يضاف بعدها (النوافذ
         الموروثة تجلب نماذجها بـAJAX). وملفان مستقلان لا كتلتان في
         `admin.js`: هذا يبقيه مقروءا، ويجعل إسقاط أحدهما سطرا واحدا.
         و`defer` يحفظ الترتيب — فـ`TQ.t()` معرفة قبل أن ينادى. */ ?>
<script src="<?php echo tq_asset('js/tqa-select.js'); ?>" defer></script>
<script src="<?php echo tq_asset('js/tqa-file.js'); ?>" defer></script>

<?php if (config_item('csrf_protection')): ?>
<script>
/**
 * توكن CSRF لنداءات AJAX — القيمة هنا، والربط في
 * [assets/taqdar/js/admin.js].
 *
 * TQ-CSRF-LOST — كان الربط هنا: `jQuery(document).ajaxSend(...)` مباشرة
 * بعد تحميل jQuery. **ولم يكن يعمل إطلاقا.**
 *
 * السبب أن `assets/backend/js/app.min.js` — حزمة قالب Hyper المحملة في
 * الذيل — **تحمل نسخة jQuery خاصة بها وتكتب فوق `window.jQuery`**
 * (أول سطر فيها هو مصنع jQuery UMD نفسه). فالمستمع المربوط أعلاه يبقى
 * على النسخة الأولى، ويصير `jQuery` الذي تستعمله الصفحة كلها نسخة
 * ثانية بسجل أحداث فارغ: `jQuery._data(document,'events').ajaxSend`
 * تساوي `undefined` في كل صفحة من صفحات اللوحة.
 *
 * وأثر ذلك ليس تفصيلا: **كل نداء AJAX غير GET في اللوحة كان يرد 403.**
 * أوضحه جدول الدورات — يرسل `POST admin/get_courses` بلا توكن فيرد
 * الخادم «الإجراء الذي طلبته غير مسموح»، ويعرض DataTables نافذة
 * `alert()` بيضاء نصها «Ajax error» فوق الصفحة. وهي نافذة نظام تحجب
 * الخيط الرئيسي حتى تغلق.
 *
 * فالربط انتقل إلى `admin.js`، وهو `defer` — أي ينفذ بعد كل سكربتات
 * الجسم، فيمسك النسخة الأخيرة من jQuery أيا كانت. وبـ`ajaxPrefilter`
 * لا `ajaxSend`: الأولى تسبق تحويل `data` إلى نص، والثانية تليه.
 */
window.TQ_CSRF = {
    name: "<?php echo $this->security->get_csrf_token_name(); ?>",
    hash: "<?php echo $this->security->get_csrf_hash(); ?>"
};
</script>

<script>
/**
 * توكن CSRF لكل نموذج POST مكتوب بيد في اللوحة.
 *
 * `csrf_protection = TRUE`، و CI3 يرفض أي POST لا يحمل التوكن بـ403
 * «الإجراء الذي طلبته غير مسموح». وفي هذا المستودع **232 وسم
 * `<form method="post">` مكتوب بيد** مقابل نداءين اثنين لـ`form_open()`
 * — أي أن CI لا يحقن الحقل إلا في اثنين منها. فكل حفظ وحذف في تلك
 * النماذج كان يرد 403.
 *
 * وتعليق `config.php` يصف الإصلاح بأنه «إضافة الحقل إلى 232 نموذجا»،
 * وهو عمل يوم كامل يفوت فيه واحد فلا يكتشف إلا بشكوى. وهذا الحقن
 * العام يلتقطها كلها في اثني عشر سطرا: عند الإرسال، وقبل مغادرة
 * الطلب، يضاف الحقل إن لم يكن موجودا.
 *
 * والنماذج الجديدة تكتب `tq_csrf()` صراحة رغم ذلك: هذا السطر يعمل
 * بـJavaScript، ونموذج يعتمد على JS ليحفظ يسقط صامتا متى تعثر ملف.
 */
(function () {
    var NAME = "<?php echo $this->security->get_csrf_token_name(); ?>";
    var HASH = "<?php echo $this->security->get_csrf_hash(); ?>";

    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!f || f.tagName !== 'FORM') return;
        if ((f.method || 'get').toLowerCase() !== 'post') return;

        // نموذج يرسل إلى مضيف آخر لا يعطى توكن هذا الموقع
        if (f.action && f.action.indexOf(location.origin) !== 0 && /^https?:/i.test(f.action)) return;

        if (f.querySelector('input[name="' + NAME + '"]')) return;

        var i = document.createElement('input');
        i.type = 'hidden';
        i.name = NAME;
        i.value = HASH;
        f.appendChild(i);
    }, true);
})();
</script>
<?php endif; ?>
