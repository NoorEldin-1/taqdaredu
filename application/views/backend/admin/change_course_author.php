<form class="required-form" action="<?php echo site_url('admin/change_course_author/'.$course_details['id']); ?>" method="post">
    <div class="tqa-field">
        <label for="parent"><?php echo get_phrase('Select an author'); ?></label>
        <select class="tqa-select" name="instructor_id" id="instructor_id" required>
            <?php foreach ($instructors as $instructor) : ?>
                <option value="<?php echo $instructor['id']; ?>" <?php if($course_details['creator'] == $instructor['id']) echo 'selected'; ?>><?php echo $instructor['first_name'].' '.$instructor['last_name']; ?> (<?php echo $instructor['email']; ?>)</option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="tqa-btn tqa-btn--primary"><?php echo get_phrase("submit"); ?></button>
</form>

<script>
    $('.select2').select2();
</script>