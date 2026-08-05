<?php
/**
 * بوّابة المعلم — رفع الدروس.
 *
 * القاعدة الحاكمة لبوّابة المعلم كلها:
 * المعلم مُسنَد إلى مادة وصفّ بعينهما، وما لم يُسنَد إليه لا يظهر في لوحته
 * أصلًا: لا محتواه ولا طلابه ولا تقاريره. والنطاق يُفرض في طبقة الاستعلام
 * لا في الواجهة — إخفاء زرّ في الواجهة ليس صلاحية.
 *
 * ولذلك لا استعلام في هذه الصفحة: القوائم كلّها تأتي من
 * `Taqdar_teacher_model` الذي يقيّدها بملكية المعلّم، وهو نفسه الذي يستقبل
 * الحفظ ويعيد التحقّق من الملكية قبل الكتابة. فمن غيّر `course_id` في
 * المتصفّح لا يكتب في كورس ليس له، لأن الحارس في الاستعلام لا في الخيارات.
 *
 * والنموذج يحفظ الآن كل ما يجمعه: العنوان والكورس والقسم والنوع والمدّة
 * والمقطع والمرفق والأهداف والملخّص. لا حقل يُجمَع ثم يُلقى.
 */

$tq_nav   = 'upload';
$tq_role  = 'teacher';
$tq_title = 'رفع الدروس';
$tq_sub   = 'درس واحد، هدف واضح، وأسئلة تقيسه';
$tq_icon  = 'upload';

/**
 * `$this` داخل العرض هو CI_Loader، وهو ينسخ خصائص المتحكّم إلى نفسه **قبل**
 * تضمين العرض. فنموذج يُحمَّل من داخل العرض يُسنَد إلى المتحكّم ولا يظهر على
 * `$this` أبدًا — ولذلك النداء هنا عبر get_instance() لا عبر `$this`.
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
   الرسائل تُقرأ بالصيغتين: المفتاح العامّ في المنصّة (flash_message /
   error_message) والمفتاح المفصَّل الذي يعيده النموذج (قائمة أخطاء +
   ما كتبه المعلّم). فأيّهما ضبطه المتحكّم عمل، ولا يفقد المعلّم مدخلاته. */
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
$tq_old_objectives = (isset($tq_old['objectives']) && is_array($tq_old['objectives']))
    ? array_values($tq_old['objectives']) : array();

$tq_type_old   = $tq_v('lesson_type', 'video');
$tq_source_old = $tq_v('video_source', 'url');

include 'portal_open.php';
?>

<div class="tq-cols">
    <div>

        <?php if ($tq_ok_msg): ?>
            <div class="tq-pastel tq-pastel--mint" role="status" style="margin-block-end:var(--tq-space-l)">
                <span class="tq-pastel__label tq-micro">تمّ</span>
                <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0"><?php echo html_escape($tq_ok_msg); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($tq_errors): ?>
            <div class="tq-pastel tq-pastel--rose" role="alert" style="margin-block-end:var(--tq-space-l)">
                <span class="tq-pastel__label tq-micro">لم يُحفَظ الدرس</span>
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
                <h2 class="tq-empty__title">لا كورس لترفع إليه بعد</h2>
                <p class="tq-empty__text">
                    الرفع يكون إلى كورس مُسنَد إليك. تواصل مع إدارة المنصّة لإسناد مادتك وصفّك،
                    وستجد هنا كورساتك وأقسامها جاهزة.
                </p>
                <a class="tq-btn tq-btn--primary" href="<?php echo base_url('teacher/courses'); ?>">عرض كورساتي</a>
            </div>

        <?php else: ?>

            <form class="tq-card tq-card--panel" method="post" enctype="multipart/form-data"
                  action="<?php echo base_url('teacher/upload/save'); ?>" data-tq-upload>

                <h2 class="tq-h2">بيانات الدرس</h2>

                <div class="tq-grid tq-grid--2">
                    <label class="tq-field">
                        <span class="tq-field__label" id="tq-lbl-course">الكورس</span>
                        <select class="tq-select" name="course_id" id="tq-course"
                                aria-labelledby="tq-lbl-course" required>
                            <?php foreach ($tq_my_courses as $tq_c): ?>
                                <option value="<?php echo (int) $tq_c['id']; ?>"
                                    <?php echo ((int) $tq_v('course_id') === (int) $tq_c['id']) ? 'selected' : ''; ?>>
                                    <?php echo html_escape($tq_c['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="tq-field">
                        <span class="tq-field__label" id="tq-lbl-section">القسم</span>
                        <select class="tq-select" name="section_id" id="tq-section" aria-labelledby="tq-lbl-section">
                            <option value="">أوّل قسم في الكورس</option>
                            <?php foreach ($tq_my_courses as $tq_c): ?>
                                <?php if (!empty($tq_sections[(int) $tq_c['id']])): ?>
                                    <optgroup label="<?php echo html_escape($tq_c['title']); ?>"
                                              data-tq-course="<?php echo (int) $tq_c['id']; ?>">
                                        <?php foreach ($tq_sections[(int) $tq_c['id']] as $tq_s): ?>
                                            <option value="<?php echo (int) $tq_s['id']; ?>"
                                                    data-tq-course="<?php echo (int) $tq_c['id']; ?>"
                                                <?php echo ((int) $tq_v('section_id') === (int) $tq_s['id']) ? 'selected' : ''; ?>>
                                                <?php echo html_escape($tq_s['title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <span class="tq-field__msg tq-field__hint">
                            إن لم يكن للكورس أقسام بعد، أُنشئ «الوحدة الأولى» وأُضيف الدرس إليها.
                        </span>
                    </label>
                </div>

                <label class="tq-field">
                    <span class="tq-field__label">عنوان الدرس</span>
                    <input class="tq-input" type="text" name="title" maxlength="140" required
                           value="<?php echo html_escape($tq_v('title')); ?>"
                           placeholder="مثال: تحليل المقدار الثلاثي إلى عوامل">
                </label>

                <fieldset style="border:0;padding:0;margin:0 0 var(--tq-space-xl)">
                    <legend class="tq-field__label" style="padding:0">نوع الدرس</legend>
                    <div class="tq-row" style="gap:var(--tq-space-l);flex-wrap:wrap">
                        <label class="tq-row" style="gap:var(--tq-space-xs)">
                            <input type="radio" name="lesson_type" value="video" data-tq-type
                                   <?php echo $tq_type_old !== 'text' ? 'checked' : ''; ?>>
                            <span>مقطع مرئي</span>
                        </label>
                        <label class="tq-row" style="gap:var(--tq-space-xs)">
                            <input type="radio" name="lesson_type" value="text" data-tq-type
                                   <?php echo $tq_type_old === 'text' ? 'checked' : ''; ?>>
                            <span>درس نصّي</span>
                        </label>
                    </div>
                </fieldset>

                <div class="tq-field" data-state="hint">
                    <label class="tq-field__label" for="tq-duration">مدّة الدرس بالدقائق</label>
                    <input class="tq-input" id="tq-duration" type="number" name="duration_minutes"
                           min="1" max="180" step="1" inputmode="numeric" required
                           value="<?php echo html_escape($tq_v('duration_minutes')); ?>"
                           style="max-inline-size:200px">
                    <span class="tq-field__msg tq-field__hint">
                        <?php echo tq_iso('القاعدة: من 8 إلى 15 دقيقة. ما زاد على ذلك يُقسَّم درسين — الانتباه ينقطع قبل الشرح.'); ?>
                    </span>
                </div>

                <fieldset style="border:0;padding:0;margin:0 0 var(--tq-space-xl)" data-tq-video-box>
                    <legend class="tq-h2" style="padding:0">مقطع الدرس</legend>

                    <div class="tq-row" style="gap:var(--tq-space-l);flex-wrap:wrap;margin-block-end:var(--tq-space-m)">
                        <label class="tq-row" style="gap:var(--tq-space-xs)">
                            <input type="radio" name="video_source" value="url" data-tq-source
                                   <?php echo $tq_source_old !== 'file' ? 'checked' : ''; ?>>
                            <span>رابط</span>
                        </label>
                        <label class="tq-row" style="gap:var(--tq-space-xs)">
                            <input type="radio" name="video_source" value="file" data-tq-source
                                   <?php echo $tq_source_old === 'file' ? 'checked' : ''; ?>>
                            <span>رفع ملفّ</span>
                        </label>
                    </div>

                    <div class="tq-field" data-tq-pane="url">
                        <label class="tq-field__label" for="tq-video-url">رابط المقطع</label>
                        <input class="tq-input tq-ltr" id="tq-video-url" type="url" name="video_url"
                               dir="ltr" placeholder="https://www.youtube.com/watch?v=..."
                               value="<?php echo html_escape($tq_v('video_url')); ?>">
                        <span class="tq-field__msg tq-field__hint">
                            يوتيوب أو Vimeo أو رابط مباشر لملفّ mp4. المشغّل يتعرّف على النوع تلقائيًّا.
                        </span>
                    </div>

                    <div class="tq-field" data-tq-pane="file">
                        <label class="tq-field__label" for="tq-video">ملفّ المقطع</label>
                        <input class="tq-input" id="tq-video" type="file" name="video"
                               accept="video/mp4,video/webm,video/quicktime,.mp4,.webm,.mov,.mkv">
                        <span class="tq-field__msg tq-field__hint">
                            <?php echo tq_iso('الصيغ المسموحة: mp4 و webm و mov و mkv. حدّ الرفع على الخادم ' . ini_get('upload_max_filesize') . '.'); ?>
                        </span>
                    </div>
                </fieldset>

                <div class="tq-field">
                    <label class="tq-field__label" for="tq-attach">مرفق الدرس (اختياري)</label>
                    <input class="tq-input" id="tq-attach" type="file" name="attachment"
                           accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.png,.jpg,.jpeg,.zip">
                    <span class="tq-field__msg tq-field__hint">ورقة تمارين أو ملخّص أو شريحة — تظهر للطالب أسفل الدرس.</span>
                </div>

                <!-- الأهداف التعليمية: الدرس بلا هدف محتوًى، لا تعلّم -->
                <fieldset style="border:0;padding:0;margin:0 0 var(--tq-space-xl)">
                    <legend class="tq-h2" style="padding:0">الأهداف التعليمية</legend>
                    <p class="tq-caption">
                        <?php echo tq_iso('لكل درس هدف واحد إلى ثلاثة، ولكل هدف 5 أسئلة على الأقل في بنك الأسئلة. الهدف يُكتب بفعل يُقاس: «يحلّل»، «يحسب»، «يميّز» — لا «يفهم».'); ?>
                    </p>

                    <?php for ($tq_i = 1; $tq_i <= 3; $tq_i++): ?>
                        <div class="tq-field">
                            <label class="tq-field__label" for="tq-obj-<?php echo $tq_i; ?>">
                                <?php echo tq_iso('الهدف ' . $tq_i . ($tq_i === 1 ? ' (مطلوب)' : ' (اختياري)')); ?>
                            </label>
                            <input class="tq-input" id="tq-obj-<?php echo $tq_i; ?>" type="text"
                                   name="objectives[]" maxlength="160"
                                   value="<?php echo html_escape(isset($tq_old_objectives[$tq_i - 1]) ? $tq_old_objectives[$tq_i - 1] : ''); ?>"
                                   <?php echo $tq_i === 1 ? 'required' : ''; ?>
                                   placeholder="<?php echo $tq_i === 1 ? 'أن يحلّل الطالب المقدار الثلاثي إلى عاملين' : ''; ?>">
                        </div>
                    <?php endfor; ?>
                </fieldset>

                <label class="tq-field">
                    <span class="tq-field__label" data-tq-summary-label>ملخّص الدرس</span>
                    <textarea class="tq-textarea" name="summary" rows="4"
                              placeholder="سطران يخبران الطالب بما سيخرج به من هذا الدرس"><?php echo html_escape($tq_v('summary')); ?></textarea>
                    <span class="tq-field__msg tq-field__hint" data-tq-summary-hint hidden>
                        الدرس النصّي محتواه هذا الملخّص، فاكتبه كاملًا.
                    </span>
                </label>

                <label class="tq-row" style="gap:var(--tq-space-xs);margin-block-end:var(--tq-space-xl)">
                    <input type="checkbox" name="is_free" value="1" <?php echo $tq_v('is_free') ? 'checked' : ''; ?>>
                    <span class="tq-caption">درس مجّاني — يُفتح لغير المشتركين كمعاينة.</span>
                </label>

                <div class="tq-row" style="gap:var(--tq-space-m);flex-wrap:wrap">
                    <button class="tq-btn tq-btn--primary" type="submit" name="action" value="published">
                        حفظ ونشر
                    </button>
                    <button class="tq-btn tq-btn--secondary" type="submit" name="action" value="review">
                        إرسال للمراجعة
                    </button>
                    <button class="tq-btn tq-btn--ghost" type="submit" name="action" value="draft">
                        حفظ كمسودّة
                    </button>
                </div>
                <p class="tq-field__msg tq-field__hint" style="margin-block-start:var(--tq-space-m)">
                    المسودّة تبقى عندك، والمراجعة تُرسل الدرس إلى الإدارة، والنشر يضعه في برنامج الطالب مباشرةً.
                </p>
            </form>

            <script>
            (function () {
                var form = document.querySelector('[data-tq-upload]');
                if (!form) return;

                /* أقسام الكورس المختار وحدها تُعرض. والخادم يتحقّق مرّة أخرى:
                   هذا تيسير على المعلّم لا حارس — الحارس في النموذج. */
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

                /* لوح المصدر: الظاهر هو المرسَل. */
                function syncSource() {
                    var v = form.querySelector('[data-tq-source]:checked');
                    var pick = v ? v.value : 'url';
                    var panes = form.querySelectorAll('[data-tq-pane]');
                    for (var i = 0; i < panes.length; i++) {
                        panes[i].hidden = panes[i].getAttribute('data-tq-pane') !== pick;
                    }
                }
                var sources = form.querySelectorAll('[data-tq-source]');
                for (var i = 0; i < sources.length; i++) sources[i].addEventListener('change', syncSource);

                /* الدرس النصّي لا مقطع له، وملخّصه هو محتواه. */
                function syncType() {
                    var t = form.querySelector('[data-tq-type]:checked');
                    var isText = t && t.value === 'text';
                    var box = form.querySelector('[data-tq-video-box]');
                    if (box) box.hidden = isText;
                    var lbl  = form.querySelector('[data-tq-summary-label]');
                    var hint = form.querySelector('[data-tq-summary-hint]');
                    if (lbl)  lbl.textContent = isText ? 'نصّ الدرس' : 'ملخّص الدرس';
                    if (hint) hint.hidden = !isText;
                    if (!isText) syncSource();
                }
                var types = form.querySelectorAll('[data-tq-type]');
                for (var j = 0; j < types.length; j++) types[j].addEventListener('change', syncType);
                syncType();

                /* الإرسال مرّة واحدة: مقطع كبير يستغرق، والنقرة الثانية تنشئ درسًا ثانيًا. */
                form.addEventListener('submit', function (e) {
                    if (form.getAttribute('data-sending')) { e.preventDefault(); return; }
                    form.setAttribute('data-sending', '1');
                    var btns = form.querySelectorAll('button[type=submit]');
                    for (var k = 0; k < btns.length; k++) btns[k].setAttribute('data-loading', 'true');
                });
            })();
            </script>

        <?php endif; ?>
    </div>

    <aside class="tq-aside">
        <div class="tq-pastel tq-pastel--peach">
            <span class="tq-pastel__label tq-micro">قاعدة الدرس الواحد</span>
            <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                <?php echo tq_iso('من 8 إلى 15 دقيقة · من 1 إلى 3 أهداف · 5 أسئلة على الأقل لكل هدف.'); ?>
            </p>
            <p class="tq-pastel__body tq-caption" style="margin:var(--tq-space-m) 0 0">
                الدرس الأطول يُقسَّم، والهدف الرابع يعني درسًا ثانيًا.
            </p>
        </div>

        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title">آخر ما رفعت</h2></div>
            <?php if ($tq_recent): ?>
                <ul class="tq-stack">
                    <?php foreach ($tq_recent as $tq_i => $tq_l): ?>
                        <?php
                        $tq_st = isset($tq_l['tq_status']) ? $tq_l['tq_status'] : 'published';
                        $tq_badge_kind = $tq_st === 'published' ? 'mastered' : ($tq_st === 'review' ? 'progress' : 'idle');
                        $tq_badge_text = $tq_st === 'published' ? 'منشور' : ($tq_st === 'review' ? 'قيد المراجعة' : 'مسودّة');
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
                    <h3 class="tq-empty__title" style="font:var(--tq-type-bodyStrong)">لا دروس بعد</h3>
                    <p class="tq-empty__text tq-caption">أول درس ترفعه يظهر هنا مع مدّته وكورسه.</p>
                    <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo base_url('teacher/courses'); ?>">كورساتي</a>
                </div>
            <?php endif; ?>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
