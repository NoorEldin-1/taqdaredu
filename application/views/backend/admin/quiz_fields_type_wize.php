<?php if($question_type == 'multiple_choice' || $question_type == 'single_choice'): ?>
    <div class="tqa-field">
        <label for="number_of_options"><?php echo get_phrase('number_of_options'); ?></label>
        <div class="input-group">
            <input type="number" value="<?php if(isset($question_details)) echo $question_details['number_of_options']; ?>" onkeyup="appendOptions(this.value, '<?php echo $question_type; ?>')" class="tqa-input" name="number_of_options" id="number_of_options" data-validate="required" data-message-required="Value Required" min="0">
        </div>
    </div>

    <div id="mcq_choice_input_options">
        <?php if(isset($question_details)): ?>
            <?php foreach(json_decode($question_details['options']) as $key => $option): ?>
                <?php $option_type = ($question_type == 'multiple_choice') ? 'checkbox':'radio'; ?>
                <?php $key++; ?>
                <div class="tqa-field options">
                    <label><?php echo get_phrase("option"); ?> <?php echo $key; ?></label>
                    <div class="input-group">
                        <input type="text" value="<?php echo $option; ?>" class="tqa-input" name = "options[]" id="option_<?php echo $key; ?>" placeholder="<?php echo get_phrase('option_'); ?><?php echo $key; ?>" required>
                        <div class="input-group-append">
                            <span class="input-group-text">
                                <input type="<?php echo $option_type; ?>" name = "correct_answers[]" value = "<?php echo $key; ?>" <?php if(in_array($key, json_decode($question_details['correct_answers']))) echo 'checked'; ?>>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script type="text/javascript">
        function appendOptions(val, question_type){
            $('#mcq_choice_input_options').html('');

            if(question_type == 'multiple_choice'){
                var optionType = "checkbox";
            }else{
                var optionType = "radio";
            }
            for(var i=1; i <= val; i++){
                var field = '<div class="tqa-field options"><label><?php echo get_phrase("option"); ?> '+i+'</label><div class="input-group"><input type="text" class="tqa-input" name = "options[]" id="option_'+i+'" placeholder="<?php echo get_phrase('option_'); ?>'+i+'" required><div class="input-group-append"><span class="input-group-text"><input type="'+optionType+'" name = "correct_answers[]" value = '+i+'></span></div></div></div>';

                $('#mcq_choice_input_options').append(field);
            }
        }
    </script>
<?php elseif($question_type == 'fill_in_the_blank'): ?>
    <?php
    /* آخر مستعمل لـ`bootstrap-tagsinput` في اللوحة — انظر TQ-TAGSINPUT-CDN
       في [tqa_tags_js.php]. والقيمة المحفوظة تفك من JSON إلى نص مفصول
       بفواصل، وهو ما يقرؤه حقل الوسوم ويكتبه. */
    $tq_answers = '';
    if (isset($question_details)) {
        $tq_decoded = json_decode($question_details['correct_answers'], true);
        if (is_array($tq_decoded)) $tq_answers = implode(',', $tq_decoded);
    }
    ?>
    <div class="tqa-field">
        <label class="tqa-field__label" for="correct_answers_in">
            <?php echo t('الكلمات التي تخفى من السؤال'); ?>
        </label>
        <div class="tqa-tags" data-tqa-tags>
            <input type="hidden" name="correct_answers" value="<?php echo html_escape($tq_answers); ?>"
                   data-tqa-tags-value>
            <input class="tqa-tags__in" type="text" id="correct_answers_in" autocomplete="off"
                   placeholder="<?php echo te('اكتب كلمة ثم اضغط Enter'); ?>" data-tqa-tags-input>
        </div>
        <span class="tqa-field__hint"><?php echo t('كل كلمة تكتبها تعرض للطالب فراغا يملؤه.'); ?></span>
    </div>

    <?php include 'tqa_tags_js.php'; ?>
<?php endif; ?>
