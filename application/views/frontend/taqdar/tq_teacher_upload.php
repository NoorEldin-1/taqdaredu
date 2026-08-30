<?php
/**
 * بوابة المعلم — رفع الدروس.
 *
 * القاعدة الحاكمة لبوابة المعلم كلها:
 * المعلم مسند إلى مادة وصف بعينهما، وما لم يسند إليه لا يظهر في لوحته
 * أصلا: لا محتواه ولا طلابه ولا تقاريره. والنطاق يفرض في طبقة الاستعلام
 * لا في الواجهة — إخفاء زر في الواجهة ليس صلاحية.
 *
 * ولذلك لا استعلام في هذه الصفحة: القوائم كلها تأتي من
 * `Taqdar_teacher_model` الذي يقيدها بملكية المعلم، وهو نفسه الذي يستقبل
 * الحفظ ويعيد التحقق من الملكية قبل الكتابة. فمن غير `course_id` في
 * المتصفح لا يكتب في كورس ليس له، لأن الحارس في الاستعلام لا في الخيارات.
 *
 * والنموذج يحفظ الآن كل ما يجمعه: العنوان والكورس والقسم والنوع والمدة
 * والمقطع والمرفق والأهداف والملخص. لا حقل يجمع ثم يلقى.
 */

$tq_nav   = 'upload';
$tq_role  = 'teacher';
$tq_title = t('رفع الدروس');
$tq_sub   = t('درس واحد، هدف واضح، وأسئلة تقيسه');
$tq_icon  = 'upload';

/**
 * `$this` داخل العرض هو CI_Loader، وهو ينسخ خصائص المتحكم إلى نفسه **قبل**
 * تضمين العرض. فنموذج يحمل من داخل العرض يسند إلى المتحكم ولا يظهر على
 * `$this` أبدا — ولذلك النداء هنا عبر get_instance() لا عبر `$this`.
 */
$CI = get_instance();

$tq_uid = (int) $CI->session->userdata('user_id');
if (!$tq_uid && isset($user_id)) $tq_uid = (int) $user_id;

$CI->load->model('taqdar_teacher_model');
$tq_model = $CI->taqdar_teacher_model;

$tq_my_courses = $tq_model->my_courses($tq_uid);
$tq_course_ids = array_map('intval', array_column($tq_my_courses, 'id'));
$tq_sections   = $tq_model->sections_of_courses($tq_course_ids);
$tq_recent     = $tq_model->recent_lessons($tq_uid, 5);

/* ---- حصيلة المحاولة السابقة ----------------------------------------
   الرسائل تقرأ بالصيغتين: المفتاح العام في المنصة (flash_message /
   error_message) والمفتاح المفصل الذي يعيده النموذج (قائمة أخطاء +
   ما كتبه المعلم). فأيهما ضبطه المتحكم عمل، ولا يفقد المعلم مدخلاته. */
$tq_ok_msg  = $CI->session->flashdata('flash_message');
$tq_err_msg = $CI->session->flashdata('error_message');
$tq_errors  = $CI->session->flashdata('tq_upload_errors');
if (!is_array($tq_errors)) $tq_errors = array();
if ($tq_err_msg && !$tq_errors) $tq_errors[] = $tq_err_msg;

$tq_old = $CI->session->flashdata('tq_upload_old');
if (!is_array($tq_old)) $tq_old = array();

/** قيمة سابقة لحقل، أو الافتراضي. */
$tq_v = function ($key, $default = '') use ($tq_old) {
    return isset($tq_old[$key]) && $tq_old[$key] !== '' ? $tq_old[$key] : $default;
};

/* وجهة قادمة من «دروسي»: `?course=&section=` تفتح النموذج والكورس والوحدة
   مختاران سلفا، فمن أراد درسا في وحدة بعينها لا يعيد اختيارها في كل مرة.
   ولا حراسة إضافية هنا: القائمتان لا تعرضان إلا ما يملكه المعلم، فمعرف
   غريب لا يطابق خيارا فلا يختار شيئا — والحفظ يعيد فحص الملكية على أي حال. */
$tq_pref_course  = (int) $CI->input->get('course');
$tq_pref_section = (int) $CI->input->get('section');
$tq_old_objectives = (isset($tq_old['objectives']) && is_array($tq_old['objectives']))
    ? array_values($tq_old['objectives']) : array();

/* الأنواع من الوحدة الموصوفة — TQ-UPLOAD-FOLD.
   لا قائمة تكتب هنا: `lesson_types()` هي المصدر، وهي نفسها التي تقرؤها
   شاشة المقرر و`save_lesson()` التي تستقبل الحفظ. */
$tq_types    = tq_cur_types();
$tq_kind_old = $tq_v('tq_kind', 'youtube');
if (!isset($tq_types[$tq_kind_old])) $tq_kind_old = 'youtube';

include 'portal_open.php';
?>

<div class="tq-cols">
    <div>

        <?php if ($tq_ok_msg): ?>
            <div class="tq-pastel tq-pastel--mint" role="status" style="margin-block-end:var(--tq-space-l)">
                <span class="tq-pastel__label tq-micro"><?php echo t('تم'); ?></span>
                <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0"><?php echo html_escape($tq_ok_msg); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($tq_errors): ?>
            <div class="tq-pastel tq-pastel--rose" role="alert" style="margin-block-end:var(--tq-space-l)">
                <span class="tq-pastel__label tq-micro"><?php echo t('لم يحفظ الدرس'); ?></span>
                <ul class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0;padding-inline-start:var(--tq-space-l)">
                    <?php foreach ($tq_errors as $tq_e): ?>
                        <li><?php echo html_escape($tq_e); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!$tq_my_courses): ?>

            <div class="tq-card tq-empty">
                <span class="tq-icon-box tq-pastel--sand" style="color:var(--tq-sand-ink)" aria-hidden="true"><?php echo tq_icon('upload', 24); ?></span>
                <h2 class="tq-empty__title"><?php echo t('لا كورس لترفع إليه بعد'); ?></h2>
                <p class="tq-empty__text">
                    <?php echo t('الرفع يكون إلى كورس مسند إليك. تواصل مع إدارة المنصة لإسناد مادتك وصفك، وستجد هنا كورساتك وأقسامها جاهزة.'); ?>
                </p>
                <a class="tq-btn tq-btn--primary" href="<?php echo base_url('teacher/courses'); ?>"><?php echo t('عرض كورساتي'); ?></a>
            </div>

        <?php else: ?>

            <form class="tq-card tq-card--panel" method="post" enctype="multipart/form-data"
                  action="<?php echo base_url('teacher/upload/save'); ?>" data-tq-upload>

                <h2 class="tq-h2"><?php echo t('بيانات الدرس'); ?></h2>

                <div class="tq-grid tq-grid--2">
                    <label class="tq-field">
                        <span class="tq-field__label" id="tq-lbl-course"><?php echo t('الكورس'); ?></span>
                        <select class="tq-select" name="course_id" id="tq-course"
                                aria-labelledby="tq-lbl-course" required>
                            <?php foreach ($tq_my_courses as $tq_c): ?>
                                <option value="<?php echo (int) $tq_c['id']; ?>"
                                    <?php echo ((int) $tq_v('course_id', $tq_pref_course) === (int) $tq_c['id']) ? 'selected' : ''; ?>>
                                    <?php echo html_escape($tq_c['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="tq-field">
                        <span class="tq-field__label" id="tq-lbl-section"><?php echo t('القسم'); ?></span>
                        <select class="tq-select" name="section_id" id="tq-section" aria-labelledby="tq-lbl-section">
                            <option value=""><?php echo t('أول قسم في الكورس'); ?></option>
                            <?php foreach ($tq_my_courses as $tq_c): ?>
                                <?php if (!empty($tq_sections[(int) $tq_c['id']])): ?>
                                    <optgroup label="<?php echo html_escape($tq_c['title']); ?>"
                                              data-tq-course="<?php echo (int) $tq_c['id']; ?>">
                                        <?php foreach ($tq_sections[(int) $tq_c['id']] as $tq_s): ?>
                                            <option value="<?php echo (int) $tq_s['id']; ?>"
                                                    data-tq-course="<?php echo (int) $tq_c['id']; ?>"
                                                <?php echo ((int) $tq_v('section_id', $tq_pref_section) === (int) $tq_s['id']) ? 'selected' : ''; ?>>
                                                <?php echo html_escape($tq_s['title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <span class="tq-field__msg tq-field__hint">
                            <?php echo t('إن لم يكن للكورس أقسام بعد، أنشئ «الوحدة الأولى» وأضيف الدرس إليها.'); ?>
                        </span>
                    </label>
                </div>

                <label class="tq-field">
                    <span class="tq-field__label"><?php echo t('عنوان الدرس'); ?></span>
                    <input class="tq-input" type="text" name="title" maxlength="140" required
                           value="<?php echo html_escape($tq_v('title')); ?>"
                           placeholder="<?php echo te('مثال: تحليل المقدار الثلاثي إلى عوامل'); ?>">
                </label>

                <?php /* ── نوع الدرس: الأنواع العشرة من الوحدة الموصوفة ──────
                         TQ-UPLOAD-FOLD. كانت هنا خانتان — «مقطع مرئي» و«درس
                         نصي» — لأن هذه الشاشة كانت تخاطب كاتب دروس ثانيا لا
                         يعرف غيرهما. فمن أراد ملفا صوتيا أو مستندا أو صورة
                         من «رفع الدروس» لم يستطع، وكان يستطيعه من شاشة
                         المقرر في التبويب المجاور.
                         والوصف الآن واحد (`lesson_types()`) والشاشتان تطبعان
                         منه، فالنوع الجديد يظهر فيهما بلا قالب يكتب. */ ?>
                <fieldset style="border:0;padding:0;margin:0 0 var(--tq-space-xl)">
                    <legend class="tq-field__label" style="padding:0"><?php echo t('نوع الدرس'); ?></legend>
                    <?php tq_cur_type_picker($tq_kind_old, 'tq'); ?>
                </fieldset>

                <?php /* حقول كل نوع تطبع كلها ويعرض المختار وحده —
                         `components/tq_lesson_panes.php` يبدل ويعطل المخفي. */ ?>
                <?php foreach ($tq_types as $tq_k => $tq_spec):
                    $tq_on = ($tq_k === $tq_kind_old); ?>
                    <div data-tqc-pane="<?php echo html_escape($tq_k); ?>" <?php echo $tq_on ? '' : 'hidden'; ?>>
                        <?php tq_cur_track_note($tq_spec, 'tq'); ?>
                        <?php tq_cur_fields($tq_spec, $tq_on ? $tq_old : array(), 'tq'); ?>
                    </div>
                <?php endforeach; ?>

                <!-- الأهداف التعليمية: الدرس بلا هدف محتوى، لا تعلم -->
                <fieldset style="border:0;padding:0;margin:0 0 var(--tq-space-xl)">
                    <legend class="tq-h2" style="padding:0"><?php echo t('الأهداف التعليمية'); ?></legend>
                    <p class="tq-caption">
                        <?php echo tq_iso(t('لكل درس هدف واحد إلى ثلاثة، ولكل هدف 5 أسئلة على الأقل في بنك الأسئلة. الهدف يكتب بفعل يقاس: «يحلل»، «يحسب»، «يميز» — لا «يفهم».')); ?>
                    </p>

                    <?php for ($tq_i = 1; $tq_i <= 3; $tq_i++): ?>
                        <div class="tq-field">
                            <label class="tq-field__label" for="tq-obj-<?php echo $tq_i; ?>">
                                <?php echo tq_iso(t('الهدف') . $tq_i . ($tq_i === 1 ? t('(مطلوب)') : t('(اختياري)'))); ?>
                            </label>
                            <input class="tq-input" id="tq-obj-<?php echo $tq_i; ?>" type="text"
                                   name="objectives[]" maxlength="160"
                                   value="<?php echo html_escape(isset($tq_old_objectives[$tq_i - 1]) ? $tq_old_objectives[$tq_i - 1] : ''); ?>"
                                   <?php echo $tq_i === 1 ? 'required' : ''; ?>
                                   placeholder="<?php echo $tq_i === 1 ? t('أن يحلل الطالب المقدار الثلاثي إلى عاملين') : ''; ?>">
                        </div>
                    <?php endfor; ?>
                </fieldset>

                <label class="tq-field">
                    <span class="tq-field__label"><?php echo t('ملخص الدرس'); ?></span>
                    <textarea class="tq-textarea" name="summary" rows="4"
                              placeholder="<?php echo te('سطران يخبران الطالب بما سيخرج به من هذا الدرس'); ?>"><?php echo html_escape($tq_v('summary')); ?></textarea>
                </label>

                <label class="tq-row" style="gap:var(--tq-space-xs);margin-block-end:var(--tq-space-xl)">
                    <input type="checkbox" name="is_free" value="1" <?php echo $tq_v('is_free') ? 'checked' : ''; ?>>
                    <span class="tq-caption"><?php echo t('درس مجاني — يفتح لغير المشتركين كمعاينة.'); ?></span>
                </label>

                <?php /* النية في حقل مخفي لا في `<button value>`: انظر
                         `components/tq_lesson_panes.php`. */ ?>
                <input type="hidden" name="action" value="draft" data-tqc-action>

                <div class="tq-row" style="gap:var(--tq-space-m);flex-wrap:wrap">
                    <button class="tq-btn tq-btn--primary" type="submit" data-tqc-submit="published">
                        <?php echo t('حفظ ونشر'); ?>
                    </button>
                    <button class="tq-btn tq-btn--secondary" type="submit" data-tqc-submit="review">
                        <?php echo t('إرسال للمراجعة'); ?>
                    </button>
                    <button class="tq-btn tq-btn--ghost" type="submit" data-tqc-submit="draft">
                        <?php echo t('حفظ كمسودة'); ?>
                    </button>
                </div>
                <p class="tq-field__msg tq-field__hint" style="margin-block-start:var(--tq-space-m)">
                    <?php echo t('المسودة تبقى عندك، والمراجعة ترسل الدرس إلى الإدارة، والنشر قرار إدارة — فما لم يفتح لك النشر المباشر يحفظ «قيد المراجعة» ويقال لك ذلك.'); ?>
                </p>
            </form>

            <script>
            (function () {
                var form = document.querySelector('[data-tq-upload]');
                if (!form) return;

                /* أقسام الكورس المختار وحدها تعرض. والخادم يتحقق مرة أخرى:
                   هذا تيسير على المعلم لا حارس — الحارس في النموذج. */
                var course  = form.querySelector('#tq-course');
                var section = form.querySelector('#tq-section');
                function syncSections() {
                    if (!course || !section) return;
                    var cid = course.value;
                    var groups = section.querySelectorAll('optgroup');
                    for (var i = 0; i < groups.length; i++) {
                        var mine = groups[i].getAttribute('data-tq-course') === cid;
                        groups[i].hidden = !mine;
                        groups[i].disabled = !mine;
                    }
                    var sel = section.options[section.selectedIndex];
                    if (sel && sel.value && sel.getAttribute('data-tq-course') !== cid) section.value = '';
                }
                if (course) course.addEventListener('change', syncSections);
                syncSections();

                /* الإرسال مرة واحدة: مقطع كبير يستغرق، والنقرة الثانية تنشئ درسا ثانيا. */
                form.addEventListener('submit', function (e) {
                    if (form.getAttribute('data-sending')) { e.preventDefault(); return; }
                    form.setAttribute('data-sending', '1');
                    var btns = form.querySelectorAll('button[type=submit]');
                    for (var k = 0; k < btns.length; k++) btns[k].setAttribute('data-loading', 'true');
                });
            })();
            </script>

            <?php $CI->load->view('components/tq_lesson_panes'); ?>

        <?php endif; ?>
    </div>

    <aside class="tq-aside">
        <div class="tq-pastel tq-pastel--peach">
            <span class="tq-pastel__label tq-micro"><?php echo t('قاعدة الدرس الواحد'); ?></span>
            <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                <?php echo tq_iso(t('من 8 إلى 15 دقيقة · من 1 إلى 3 أهداف · 5 أسئلة على الأقل لكل هدف.')); ?>
            </p>
            <p class="tq-pastel__body tq-caption" style="margin:var(--tq-space-m) 0 0">
                <?php echo t('الدرس الأطول يقسم، والهدف الرابع يعني درسا ثانيا.'); ?>
            </p>
        </div>

        <div class="tq-card">
            <div class="tq-card__head">
                <h2 class="tq-card__title"><?php echo t('آخر ما رفعت'); ?></h2>
                <?php /* خمسة وحدها لا تكفي معلما عنده أربعون درسا — والقائمة
                         الكاملة صارت لها شاشة. */ ?>
                <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('teacher/lessons'); ?>"><?php echo t('كل دروسي'); ?></a>
            </div>
            <?php if ($tq_recent): ?>
                <ul class="tq-stack">
                    <?php foreach ($tq_recent as $tq_i => $tq_l): ?>
                        <?php
                        $tq_st = isset($tq_l['tq_status']) ? $tq_l['tq_status'] : 'published';
                        $tq_badge_kind = $tq_st === 'published' ? 'mastered' : ($tq_st === 'review' ? 'progress' : 'idle');
                        $tq_badge_text = $tq_st === 'published' ? t('منشور') : ($tq_st === 'review' ? t('قيد المراجعة') : t('مسودة'));
                        ?>
                        <li class="tq-row" style="gap:var(--tq-space-m)">
                            <span class="tq-icon-box tq-pastel--<?php echo tq_pastel($tq_i); ?>" aria-hidden="true"><?php echo tq_icon('play'); ?></span>
                            <span style="flex:1;min-inline-size:0">
                                <span class="tq-strong" style="display:block;color:var(--tq-navy)"><?php echo html_escape($tq_l['title']); ?></span>
                                <span class="tq-micro">
                                    <?php echo html_escape($tq_l['course_title']); ?>
                                    <?php if (!empty($tq_l['duration'])): ?>
                                        · <?php echo tq_iso($tq_l['duration']); ?>
                                    <?php endif; ?>
                                </span>
                            </span>
                            <?php echo tq_badge($tq_badge_kind, $tq_badge_text); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="tq-empty" style="padding:var(--tq-space-l) 0">
                    <span class="tq-icon-box tq-pastel--lilac" style="color:var(--tq-lilac-ink)" aria-hidden="true"><?php echo tq_icon('file', 24); ?></span>
                    <h3 class="tq-empty__title" style="font:var(--tq-type-bodyStrong)"><?php echo t('لا دروس بعد'); ?></h3>
                    <p class="tq-empty__text tq-caption"><?php echo t('أول درس ترفعه يظهر هنا مع مدته وكورسه.'); ?></p>
                    <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo base_url('teacher/lessons'); ?>"><?php echo t('كل دروسي'); ?></a>
                </div>
            <?php endif; ?>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
