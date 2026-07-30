<script src="<?php echo base_url() . 'assets/frontend/default-new/js/bootstrap.bundle.min.js'; ?>"></script>
<!-- PERFORMANCE: Load reflow optimizer before plugins that use it -->
<script src="<?php echo base_url() . 'assets/frontend/default-new/js/reflow_optimizer.js'; ?>"></script>
<script defer src="<?php echo base_url() . 'assets/frontend/default-new/js/berli.js'; ?>"></script>
<script defer src="<?php echo base_url() . 'assets/frontend/default-new/js/course.js'; ?>"></script>
<script defer src="<?php echo base_url() . 'assets/frontend/default-new/js/jquery.meanmenu.min.js'; ?>"></script>
<script defer src="<?php echo base_url() . 'assets/frontend/default-new/js/jquery.nice-select.min.js'; ?>"></script>
<script defer src="<?php echo base_url() . 'assets/frontend/default-new/js/jquery.webui-popover.min.js'; ?>"></script>

<?php
// Conditional loading: Only load Owl Carousel on homepage
if (isset($page_name) && $page_name === 'home_1'):
	?>
	<script src="<?php echo base_url() . 'assets/frontend/default-new/js/owl.carousel.min.js'; ?>"></script>
<?php endif; ?>

<script defer src="<?php echo base_url() . 'assets/frontend/default-new/js/script-2.js'; ?>"></script>
<script defer src="<?php echo base_url() . 'assets/frontend/default-new/js/slick.min.js'; ?>"></script>
<script defer src="<?php echo base_url() . 'assets/frontend/default-new/js/venobox.min.js'; ?>"></script>

<!-- REMOVED: WOW.js (8.4KB) - Replaced with lightweight IntersectionObserver -->
<script defer src="<?php echo base_url() . 'assets/frontend/default-new/js/lightweight_animations.js'; ?>"></script>

<!-- PERFORMANCE: Lazy carousel initializer - Eliminates forced reflows -->
<script defer src="<?php echo base_url() . 'assets/frontend/default-new/js/lazy_carousel_init.js'; ?>"></script>

<script defer src="<?php echo base_url() . 'assets/frontend/default-new/js/script.js'; ?>"></script>

<?php
// Conditional loading: Only load Summernote on pages that need rich text editing
$summernote_pages = ['course_form', 'lesson_form', 'quiz_questions', 'blog_form', 'page_form'];
$load_summernote = false;

// Check if we're on a page that needs Summernote
if (isset($page_name) && in_array($page_name, $summernote_pages)) {
	$load_summernote = true;
}

// Also load on any page with .text_editor class (dynamic check)
if (isset($page_name) && strpos($page_name, 'admin') !== false) {
	$load_summernote = true;
}

if ($load_summernote):
	?>
	<script
		src="<?php echo base_url() . 'assets/frontend/default-new/summernote-0.8.20-dist/summernote-lite.min.js'; ?>"></script>
<?php endif; ?>

<script src="<?php echo base_url() . 'assets/global/toastr/toastr.min.js'; ?>"></script>
<script src="<?php echo base_url() . 'assets/global/jquery-form/jquery.form.min.js'; ?>"></script>
<script src="<?php echo base_url() . 'assets/global/tagify/jquery.tagify.js'; ?>"></script>

<!-- Deferred Chat Widget - Loads on user interaction (or after 10s) -->
<script async src="<?php echo base_url() . 'assets/frontend/default-new/js/deferred_chat.js'; ?>"></script>

<!-- SHOW TOASTR NOTIFIVATION -->
<?php if ($this->session->flashdata('flash_message') != ""): ?>

	<script type="text/javascript">
		toastr.success('<?php echo $this->session->flashdata("flash_message"); ?>');
	</script>

<?php endif; ?>

<?php if ($this->session->flashdata('error_message') != ""): ?>

	<script type="text/javascript">
		toastr.error('<?php echo $this->session->flashdata("error_message"); ?>');
	</script>

<?php endif; ?>

<?php if ($this->session->flashdata('info_message') != ""): ?>

	<script type="text/javascript">
		toastr.info('<?php echo $this->session->flashdata("info_message"); ?>');
	</script>

<?php endif; ?>