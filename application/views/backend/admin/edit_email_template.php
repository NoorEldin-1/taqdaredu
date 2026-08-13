<form action="<?php echo site_url('admin/edit_email_template/'.$notification['id'].'/update'); ?>" method="post">
	<?php foreach(json_decode($notification['subject'], true) as $user_type => $subject): ?>
		<div class="tqa-field">
			<label for="<?= 'subject_label_'.$user_type ?>"><?php echo get_phrase('Email subject'); ?> <small>(<?php echo get_phrase('To '.$user_type); ?></small>)</label>
			<input type="text" name="subject[<?= $user_type; ?>]" id="<?= 'subject_label_'.$user_type ?>" value="<?= $subject; ?>" class="tqa-input">
		</div>
	<?php endforeach; ?>

	<?php foreach(json_decode($notification['template'], true) as $user_type => $template): ?>
		<div class="tqa-field">
			<label for="<?= 'template_label_'.$user_type ?>"><?php echo get_phrase('Email template'); ?> <small>(<?php echo get_phrase('To '.$user_type); ?></small>)</label>

			<textarea name="template[<?= $user_type; ?>]" id="<?= 'template_label_'.$user_type ?>" class="tqa-input"  rows="4"><?= $template; ?></textarea>
		</div>
	<?php endforeach; ?>

	<div class="tqa-field">
		<button type="submit" class="tqa-btn tqa-btn--primary"><?php echo get_phrase('Save changes'); ?></button>
	</div>
</form>

<script type="text/javascript">
	initSummerNote(['textarea']);
</script>