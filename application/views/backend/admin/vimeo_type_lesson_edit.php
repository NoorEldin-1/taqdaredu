<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<input type="hidden" name="lesson_type" value="video-url">
<input type="hidden" name="lesson_provider" value="vimeo">
<?php
$tq_url = $lesson_details['video_url'];
$tq_dur = $lesson_details['duration'];
include '_tq_videourl_fields.php';
include '_tq_mobile_carry.php';
?>
