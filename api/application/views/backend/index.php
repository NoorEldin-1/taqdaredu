<?php
    $system_name = $this->db->get_where('settings' , array('key'=>'system_name'))->row()->value;
    $system_title = $this->db->get_where('settings' , array('key'=>'system_title'))->row()->value;
    $user_details = $this->user_model->get_all_user($this->session->userdata('user_id'))->row_array();
    $text_align     = $this->db->get_where('settings', array('key' => 'text_align'))->row()->value;
    $logged_in_user_role = strtolower($this->session->userdata('role'));
?>
<!DOCTYPE html>
<!-- The panel is Arabic (settings.language), so the document direction is RTL.
     The vendor theme is Bootstrap 4 LTR and has no RTL build, so the mirroring
     is done in assets/backend/css/tagdar.css, loaded last in includes_top.php. -->
<html lang="ar" dir="rtl">
<head>
    <title><?php echo get_phrase($page_title); ?> | <?php echo $system_title; ?></title>
    <!-- all the meta tags -->
    <?php include 'metas.php'; ?>
    <!-- all the css files -->
    <?php include 'includes_top.php'; ?>
    
    <!-- TODO(client): a Google Tag Manager container belonging to the PREVIOUS
         owner (GTM-M4M87L2R) used to load here and on every admin page, sending
         this panel's activity to their analytics property. It has been removed.
         Add Tagdar's own GTM/analytics ID here if and when they have one. -->
</head>
<body data-layout="detached">

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
