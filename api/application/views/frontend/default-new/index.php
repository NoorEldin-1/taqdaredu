<?php
$language_dir = 'ltr';
$language_dirs = get_settings('language_dirs');
if ($language_dirs) {
	$current_language = $this->session->userdata('language');
	$language_dirs_arr = json_decode($language_dirs, true);
	if (array_key_exists($current_language, $language_dirs_arr)) {
		$language_dir = $language_dirs_arr[$current_language];
	}
}

?>
<!DOCTYPE html>
<html lang="<?php echo getIsoCode('english'); ?>" dir="<?php echo $language_dir; ?>">

<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5.0, minimum-scale=0.86">

	<?php include 'seo.php'; ?>



	<link rel="icon" href="<?php echo base_url('uploads/system/' . get_frontend_settings('favicon')); ?>"
		type="image/x-icon">
	<link rel="apple-touch-icon" sizes="180x180"
		href="<?php echo base_url('uploads/system/' . get_frontend_settings('favicon')); ?>">

	<?php include 'includes_top.php'; ?>

	<style type="text/css">
		<?php echo get_frontend_settings('custom_css'); ?>
	</style>


	<!-- Google Tag Manager - DEFERRED -->
	<!-- Loads 3 seconds after page load to prevent blocking initial render -->
	<!-- Now gated behind the gtm_container_id setting (empty = off). GTM's ad/
	     remarketing tags pull Google scripts that call deprecated Privacy Sandbox
	     APIs (Shared Storage / Protected Audience), which flagged the page in
	     PageSpeed "Best Practices". Set gtm_container_id in settings to re-enable. -->
	<?php if (!empty(get_settings('gtm_container_id'))): ?>
	<script>
		window.addEventListener('load', function () {
			setTimeout(function () {
				(function (w, d, s, l, i) {
					w[l] = w[l] || []; w[l].push({
						'gtm.start':
							new Date().getTime(), event: 'gtm.js'
					}); var f = d.getElementsByTagName(s)[0],
						j = d.createElement(s), dl = l != 'dataLayer' ? '&l=' + l : ''; j.async = true; j.src =
							'https://www.googletagmanager.com/gtm.js?id=' + i + dl; f.parentNode.insertBefore(j, f);
				})(window, document, 'script', 'dataLayer', '<?php echo get_settings('gtm_container_id'); ?>');
			}, 3000); // Delay 3 seconds after page load
		});
	</script>
	<?php endif; ?>
	<!-- End Google Tag Manager -->


</head>

<body class="<?php echo $this->session->userdata('theme_mode'); ?>">
	<!-- Google Tag Manager (noscript) -->
	<?php if (!empty(get_settings('gtm_container_id'))): ?>
	<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo get_settings('gtm_container_id'); ?>" height="0" width="0"
			style="display:none;visibility:hidden"></iframe></noscript>
	<?php endif; ?>
	<!-- End Google Tag Manager (noscript) -->

	<?php
	//user wishlist items
	$my_wishlist_items = array();
	if ($user_id = $this->session->userdata('user_id')) {
		$wishlist = $this->user_model->get_all_user($user_id)->row('wishlist');
		if ($wishlist != '') {
			$my_wishlist_items = json_decode($wishlist, true);
		}
	}

	if ($this->session->userdata('app_url')):
		include "go_back_to_mobile_app.php";
	endif;

	include 'header.php';

	if (get_frontend_settings('cookie_status') == 'active'):
		include 'eu-cookie.php';
	endif;

	if ($page_name === null) {
		include $path;
	} else {
		include $page_name . '.php';
	}
	include 'footer.php';
	include 'includes_bottom.php';
	include 'modal.php';
	include 'common_scripts.php';
	include 'init.php';
	?>

	<?php echo get_frontend_settings('embed_code'); ?>
</body>

</html>