<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<input type="hidden" name="lesson_type" value="other-iframe">

<div class="tqa-field">
    <label class="tqa-field__label" for="iframe_source">
        كود التضمين أو رابطه <span class="tqa-field__req" aria-hidden="true">*</span>
    </label>
    <textarea class="tqa-textarea tqa-input--ltr" id="iframe_source" name="iframe_source" rows="3"
              dir="ltr" spellcheck="false" required><?php
        echo html_escape($lesson_details['attachment']); ?></textarea>
    <span class="tqa-field__hint">
        يقبل وسم <span class="tq-ltr" dir="ltr">&lt;iframe&gt;</span> كاملا، أو الرابط وحده.
    </span>
</div>
