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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-tagsinput/0.8.0/bootstrap-tagsinput.css">

<script src="<?php echo base_url('assets/backend/js/jquery-3.3.1.min.js'); ?>" charset="utf-8"></script>
<script src="<?php echo site_url('assets/backend/js/onDomChange.js');?>"></script>

<?php /* هوية تقدّر: التوكنات والخطوط ثم طبقة اللوحة — تُحمَّل بعد CSS القالب فتغلبه. */ ?>
<link rel="preload" as="font" type="font/woff2" crossorigin href="<?php echo tq_asset('site/fonts/Cairo-700-arabic.woff2'); ?>">
<link rel="preload" as="font" type="font/woff2" crossorigin href="<?php echo tq_asset('site/fonts/Plex-400-arabic.woff2'); ?>">
<link rel="stylesheet" href="<?php echo tq_asset('css/fonts.css'); ?>">
<link rel="stylesheet" href="<?php echo tq_asset('css/tokens.css'); ?>">
<link rel="stylesheet" href="<?php echo tq_asset('css/admin.css'); ?>">

<?php /* توكن CSRF لكل نداء AJAX في اللوحة — يلتقط الـ82 نداءً بلا لمس أيٍّ منها. */ ?>
<?php if (config_item('csrf_protection')): ?>
<script>
(function () {
    var NAME = "<?php echo $this->security->get_csrf_token_name(); ?>";
    var HASH = "<?php echo $this->security->get_csrf_hash(); ?>";
    if (!window.jQuery) return;

    jQuery(document).ajaxSend(function (e, xhr, opts) {
        var m = (opts.type || opts.method || "GET").toUpperCase();
        if (m === "GET" || m === "HEAD" || m === "OPTIONS") return;
        if (opts.crossDomain) return;

        if (opts.data instanceof FormData) {
            if (!opts.data.has(NAME)) opts.data.append(NAME, HASH);
            return;
        }
        if (typeof opts.data === "string") {
            if (opts.data.indexOf(NAME + "=") === -1) {
                opts.data += (opts.data.length ? "&" : "") + NAME + "=" + encodeURIComponent(HASH);
            }
            return;
        }
        opts.data = jQuery.extend({}, opts.data || {});
        if (!(NAME in opts.data)) opts.data[NAME] = HASH;
    });
})();
</script>
<?php endif; ?>
