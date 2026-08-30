<?php tqa_head(t('إعدادات المعلمين'), t('ما يستطيع المعلم فعله بنفسه، ونسبته من كل عملية بيع.'), 'graduation'); ?>

<div class="tqa-stack">
    <div>
        <div class="tqa-card">
            <div class="tqa-card__body">
                <h4 class="mb-3 header-title"><?php echo get_phrase('public_instructor_settings');?></h4>

                <form action="<?php echo site_url('admin/instructor_settings/update'); ?>" method="post" enctype="multipart/form-data">
                    <div class="tqa-field">
                        <label><?php echo get_phrase('allow_public_instructor'); ?></label>
                        <select class="tqa-select" name="allow_instructor" required>
                            <option value="1" <?php if(get_settings('allow_instructor') == 1) echo 'selected'; ?>><?php echo get_phrase('yes'); ?></option>
                            <option value="0" <?php if(get_settings('allow_instructor') == 0) echo 'selected'; ?>><?php echo get_phrase('no'); ?></option>
                        </select>
                    </div>
                    <div class="tqa-field">
                        <label for="instructor_application_note"><?php echo get_phrase('instructor_application_note'); ?></label>
                        <div class="tqa-field">
                            <textarea class="tqa-input" name="instructor_application_note" rows="8" cols="80"><?php echo get_settings('instructor_application_note'); ?></textarea>
                        </div>
                    </div>

                    <div class="tqa-stack">
                        <div>
                            <button type="submit" class="tqa-btn tqa-btn--primary tqa-btn--block"><?php echo get_phrase('update_settings'); ?></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div>
        <div class="tqa-card">
            <div class="tqa-card__body">
                <h4 class="mb-3 header-title"><?php echo get_phrase('instructor_commission_settings');?></h4>

                <form action="<?php echo site_url('admin/instructor_settings/update'); ?>" method="post" enctype="multipart/form-data">
                    <div class="tqa-field">
                        <label for="instructor_revenue"><?php echo get_phrase('instructor_revenue_percentage'); ?></label>
                        <div class="input-group">
                            <input type="number" name = "instructor_revenue" id = "instructor_revenue" class="tqa-input" onkeyup="calculateAdminRevenue(this.value)" min="0" max="100" value="<?php echo get_settings('instructor_revenue'); ?>">
                            <div class="input-group-append">
                                <span class="input-group-text"><i class="mdi mdi-percent"></i></span>
                            </div>
                        </div>
                    </div>
                    <div class="tqa-field">
                        <label for="admin_revenue"><?php echo get_phrase('admin_revenue_percentage'); ?></label>
                        <div class="input-group">
                            <input type="number" name = "admin_revenue" id = "admin_revenue" class="tqa-input" value="0" disabled style="background: none; cursor: default;">
                            <div class="input-group-append">
                                <span class="input-group-text"><i class="mdi mdi-percent"></i></span>
                            </div>
                        </div>
                    </div>

                    <div class="tqa-stack">
                        <div>
                            <button type="submit" class="tqa-btn tqa-btn--primary tqa-btn--block"><?php echo get_phrase('update_settings'); ?></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        var instructor_revenue = $('#instructor_revenue').val();
        calculateAdminRevenue(instructor_revenue);
    });
    function calculateAdminRevenue(instructor_revenue) {
        if(instructor_revenue <= 100){
            var admin_revenue = 100 - instructor_revenue;
            $('#admin_revenue').val(admin_revenue);
        }else {
            $('#admin_revenue').val(0);
        }
    }
</script>
