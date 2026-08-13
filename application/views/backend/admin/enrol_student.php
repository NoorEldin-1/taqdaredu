<?php tqa_head('تسجيل الطلاب في كورس', 'التسجيل اليدوي — يفتح الكورس للطالب بلا دفع.', 'clipboard'); ?>

<div class="tqa-stack">
    <div>
        <div class="tqa-card">
            <div class="tqa-card__body">
              <div>
                <h4 class="mb-3 header-title"><?php echo get_phrase('enrolment_form'); ?></h4>

                <form class="required-form" action="<?php echo site_url('admin/enrol_student/enrol'); ?>" method="post" enctype="multipart/form-data">

                    <?php
                    /* TQ-EMPTY-SELECT — كان هذا المنتقي **فارغا تماما**:
                       `<select class="server-side-select2"></select>` بلا خيار
                       واحد، ينتظر select2 ليملأه بـAJAX — و select2 غير محمل
                       في اللوحة (انظر TQ-SELECT2-GONE). أي أن تسجيل طالب في
                       دورة من اللوحة كان **متعذرا**: الحقل مطلوب ولا قيمة فيه.

                       فيملأ من الخادم، ويرشح بصندوق نص بلا مكتبة. */
                    $tq_users = $this->db->select('id, first_name, last_name, email')
                                         ->where('role_id', 2)->where('status', 1)
                                         ->order_by('first_name', 'ASC')->limit(2000)
                                         ->get('users')->result_array();
                    ?>
                    <div class="tqa-field">
                        <label for="multiple_user_id"><?php echo get_phrase('users'); ?><span class="required">*</span> </label>
                        <input type="search" class="tqa-input mb-2" data-tqa-filter="#multiple_user_id"
                               placeholder="اكتب للترشيح بالاسم أو البريد…" autocomplete="off">
                        <select class="tqa-input" name="user_id[]" id="multiple_user_id"
                                multiple="multiple" size="10" required>
                            <?php foreach ($tq_users as $tq_u): ?>
                                <option value="<?php echo (int) $tq_u['id']; ?>"><?php
                                    echo html_escape(trim($tq_u['first_name'] . ' ' . $tq_u['last_name']));
                                    echo ' — ' . html_escape($tq_u['email']);
                                ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="tqa-field__hint">اختر واحدا أو أكثر — بـCtrl أو ⌘ للاختيار المتعدد.</small>
                    </div>

                    <div class="tqa-field">
                        <label for="multiple_course_id"><?php echo get_phrase('course_to_enrol'); ?><span class="required">*</span> </label>
                        <input type="search" class="tqa-input mb-2" data-tqa-filter="#multiple_course_id"
                               placeholder="اكتب للترشيح باسم الدورة…" autocomplete="off">
                        <select class="tqa-input" multiple="multiple" size="8" name="course_id[]" id="multiple_course_id" required>
                            <?php /* بلا خيار «اختر دورة» فارغ: في منتق متعدد يصير
                                     سطرا يختار فيرسل قيمة خالية. */ ?>
                            <?php $course_list = $this->db->where('status', 'active')->or_where('status', 'private')->get('course')->result_array();
                                foreach ($course_list as $course): ?>
                                <option value="<?php echo $course['id'] ?>"><?php echo $course['title']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="button" class="tqa-btn tqa-btn--primary" onclick="checkRequiredFields()"><?php echo get_phrase('enrol_student'); ?></button>
                </form>
              </div>
            </div> <!-- end card body-->
        </div> <!-- end card -->
    </div><!-- end col-->
</div>
