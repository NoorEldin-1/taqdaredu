<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<input type="hidden" name="lesson_type" value="text-description">

<div class="tqa-field">
    <label class="tqa-field__label" for="text_description">
        نص الدرس <span class="tqa-field__req" aria-hidden="true">*</span>
    </label>
    <textarea class="tqa-textarea" id="text_description" name="text_description" rows="8"
              required data-tqa-rich></textarea>
</div>
