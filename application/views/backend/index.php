<?php
    $system_name = $this->db->get_where('settings' , array('key'=>'system_name'))->row()->value;
    $system_title = $this->db->get_where('settings' , array('key'=>'system_title'))->row()->value;
    $user_details = $this->user_model->get_all_user($this->session->userdata('user_id'))->row_array();
    $text_align     = $this->db->get_where('settings', array('key' => 'text_align'))->row()->value;
    $logged_in_user_role = strtolower($this->session->userdata('role'));
?>
<?php
    /* الاتجاه نتيجة للغة لا إعداد مستقل — نفس قاعدة الواجهة الأمامية.
       وصنف `tqa` على body هو ما تتعلق به طبقة هوية تقدر، فلا تعدل
       أي شاشة من التسعين شاشة القديمة. */
    $tqa_lang = get_settings('language') ?: 'arabic';
    $tqa_dirs = json_decode(get_settings('language_dirs') ?: '{}', true);
    $tqa_active = $this->session->userdata('language') ?: $tqa_lang;
    $tqa_dir = $tqa_dirs[$tqa_active] ?? 'ltr';
    $tqa_iso = getIsoCode(ucfirst($tqa_active)) ?: 'ar';
?>
<!DOCTYPE html>
<html lang="<?php echo html_escape($tqa_iso); ?>" dir="<?php echo html_escape($tqa_dir); ?>">
<head>
    <title><?php echo get_phrase($page_title); ?> | <?php echo $system_title; ?></title>
    <!-- all the meta tags -->
    <?php include 'metas.php'; ?>
    <!-- all the css files -->
    <?php include 'includes_top.php'; ?>
</head>
<body class="tqa" data-layout="detached">
    <!-- HEADER -->
    <?php include 'header.php'; ?>
    <div class="container-fluid">
        <div class="wrapper">
            <!-- BEGIN CONTENT -->
            <!-- SIDEBAR -->
            <?php include $logged_in_user_role.'/'.'navigation.php' ?>
            <!-- PAGE CONTAINER-->
            <div class="content-page">
                <div class="content">
                    <!-- BEGIN PlACE PAGE CONTENT HERE -->
                    <?php include $logged_in_user_role.'/'.$page_name.'.php';?>
                    <!-- END PLACE PAGE CONTENT HERE -->
                </div>
            </div>
            <!-- END CONTENT -->
        </div>
    </div>
    <!-- all the js files -->
    <?php include 'includes_bottom.php'; ?>
    <?php include 'modal.php'; ?>
    <?php include 'common_scripts.php'; ?>
</body>
</html>
