<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * إعدادات ولي الأمر.
 *
 * كانت هذه الشاشة عرضا بلا نموذج واحد: لا تعديل بيانات، ولا تفضيلات،
 * وزر «تغيير كلمة المرور» يشير إلى `/profile` وهو يرجع 500 — فالمستخدم
 * يصطدم بجدار. صارت الآن ثلاثة نماذج تكتب فعلا عبر `Taqdar_parent_model`:
 * بياناتك · كلمة مرورك · ما يصلك ومتى.
 *
 * والربط بالابن **بموافقة موثقة** لا بتخمين: يطلب من هنا (أو من شاشة
 * «أبنائي»)، ويبدأ `pending` بلا تاريخ، ولا يصير `active` إلا بموافقة الابن
 * من حسابه وبتاريخ في `consent_at` — وهو شرط مفروض في القاعدة نفسها لا في
 * الشيفرة وحدها. وحدود الرؤية معروضة صراحة لا مخفية: ولي الأمر يعرف
 * ما لا يراه، فالشفافية مع الأسرة لا تعني إلغاء مساحة الطالب.
 */

$tq_ci = &get_instance();   // الطبقة المحملة داخل عرض لا تنسخ على $this، فتؤخذ من النسخة
$tq_ci->load->model('taqdar_parent_model');
$tq_pm = $tq_ci->taqdar_parent_model;
/* النماذج تنشر إلى `POST parent/settings/save` و`POST parent/children/link`
   في المتحكم، وكلاهما يستدعي دوال هذا النموذج. وهذا النداء شبكة أمان
   لأي نشر يصل إلى برنامج الشاشة نفسه. */
$tq_pm->handle_post('settings');

$tq_nav   = 'settings';
$tq_role  = 'parent';
$tq_title = 'الإعدادات';
$tq_sub   = 'حسابك، وروابط أبنائك، وما يصلك ومتى';
$tq_icon  = 'cog';

$tq_uid   = (int) $this->session->userdata('user_id');
$u        = $tq_pm->profile($tq_uid);
$tq_links = $tq_pm->links($tq_uid);
$tq_prefs = $tq_pm->prefs($tq_uid);
$tq_types = $tq_pm->notify_keys();

include 'portal_open.php';
?>

<?php echo $tq_pm->flash_html(); ?>

<div class="tq-cols">
    <div class="tq-stack">

        <!-- بياناتك -->
        <section class="tq-card">
            <h2 class="tq-card__title">بياناتك</h2>
            <form method="post" action="<?php echo base_url('parent/settings/save'); ?>">
                <?php echo tq_csrf(); ?>
                <input type="hidden" name="tq_action" value="profile_save">

                <div class="tq-grid tq-grid--2">
                    <div class="tq-field">
                        <label class="tq-field__label" for="tq-first">الاسم الأول</label>
                        <input class="tq-input" id="tq-first" name="first_name" type="text" required
                               value="<?php echo html_escape((string) ($u['first_name'] ?? '')); ?>">
                    </div>
                    <div class="tq-field">
                        <label class="tq-field__label" for="tq-last">اسم العائلة</label>
                        <input class="tq-input" id="tq-last" name="last_name" type="text" required
                               value="<?php echo html_escape((string) ($u['last_name'] ?? '')); ?>">
                    </div>
                </div>

                <div class="tq-field">
                    <label class="tq-field__label" for="tq-email">البريد الإلكتروني</label>
                    <input class="tq-input tq-ltr" id="tq-email" name="email" type="email" required
                           value="<?php echo html_escape((string) ($u['email'] ?? '')); ?>">
                    <p class="tq-field__hint">هو نفسه اسم دخولك — وتغييره يغير ما تدخل به.</p>
                </div>

                <div class="tq-grid tq-grid--2">
                    <div class="tq-field">
                        <label class="tq-field__label" for="tq-phone">الجوال</label>
                        <input class="tq-input tq-ltr" id="tq-phone" name="phone" type="text" inputmode="tel"
                               value="<?php echo html_escape((string) ($u['phone'] ?? '')); ?>">
                    </div>
                    <div class="tq-field">
                        <label class="tq-field__label" for="tq-address">المدينة أو العنوان</label>
                        <input class="tq-input" id="tq-address" name="address" type="text"
                               value="<?php echo html_escape((string) ($u['address'] ?? '')); ?>">
                    </div>
                </div>

                <button class="tq-btn tq-btn--primary" type="submit">حفظ بياناتك</button>
            </form>
        </section>

        <!-- كلمة المرور -->
        <section class="tq-card">
            <h2 class="tq-card__title">كلمة المرور</h2>
            <form method="post" action="<?php echo base_url('parent/settings/save'); ?>">
                <?php echo tq_csrf(); ?>
                <input type="hidden" name="tq_action" value="password_change">

                <div class="tq-field">
                    <label class="tq-field__label" for="tq-cur-pass">كلمة المرور الحالية</label>
                    <input class="tq-input tq-ltr" id="tq-cur-pass" name="current_password" type="password"
                           autocomplete="current-password" required>
                </div>
                <div class="tq-grid tq-grid--2">
                    <div class="tq-field">
                        <label class="tq-field__label" for="tq-new-pass">كلمة المرور الجديدة</label>
                        <input class="tq-input tq-ltr" id="tq-new-pass" name="new_password" type="password"
                               autocomplete="new-password" minlength="6" required>
                    </div>
                    <div class="tq-field">
                        <label class="tq-field__label" for="tq-new-pass2">تأكيد الجديدة</label>
                        <input class="tq-input tq-ltr" id="tq-new-pass2" name="confirm_password" type="password"
                               autocomplete="new-password" minlength="6" required>
                    </div>
                </div>

                <button class="tq-btn tq-btn--primary" type="submit">تغيير كلمة المرور</button>
                <p class="tq-micro" style="margin-block-start:var(--tq-space-m)">
                    نسيتها؟
                    <a href="<?php echo site_url('login/forgot_password_request'); ?>">أرسل رابط تغييرها إلى بريدك</a>.
                </p>
            </form>
        </section>

        <!-- روابط الأبناء -->
        <section class="tq-card">
            <h2 class="tq-card__title">روابط الأبناء</h2>

            <?php if (empty($tq_links)): ?>
                <p class="tq-caption">لا رابط في حسابك بعد. أرسل طلبك من النموذج تحت.</p>
            <?php else: ?>
                <?php foreach ($tq_links as $l):
                    $k = $l['status'] === 'active' ? 'mastered' : ($l['status'] === 'pending' ? 'due' : 'late');
                    $w = ['active' => 'مفعل', 'pending' => 'بانتظار موافقته', 'revoked' => 'مسحوب'][$l['status']] ?? $l['status'];
                ?>
                    <div class="tq-prefrow">
                        <span class="tq-prefrow__main">
                            <span class="tq-prefrow__title"><?php echo html_escape($l['name']); ?></span>
                            <span class="tq-prefrow__hint tq-ltr" style="direction:ltr;text-align:start"><?php echo html_escape((string) $l['email']); ?></span>
                            <span class="tq-prefrow__hint">
                                <?php if (!empty($l['consent_at']) && $l['status'] === 'active'): ?>
                                    وافق بتاريخ <?php echo TQ_LRI . html_escape((string) $l['consent_at']) . TQ_PDI; ?>
                                <?php elseif ($l['status'] === 'pending'): ?>
                                    لا تاريخ موافقة بعد — لا يفتح شيء من بياناته
                                <?php elseif (!empty($l['prefs']['rejected']['at'])): ?>
                                    رفض الطلب بتاريخ <?php echo TQ_LRI . html_escape((string) $l['prefs']['rejected']['at']) . TQ_PDI; ?>
                                <?php elseif (!empty($l['prefs']['revoked']['at'])): ?>
                                    <?php $tq_by_student = ($l['prefs']['revoked']['by_role'] ?? '') === 'student'; ?>
                                    <?php echo $tq_by_student ? 'سحب موافقته بتاريخ ' : 'ألغيت الربط بتاريخ '; ?>
                                    <?php echo TQ_LRI . html_escape((string) $l['prefs']['revoked']['at']) . TQ_PDI; ?>
                                <?php endif; ?>
                            </span>
                        </span>
                        <span class="tq-prefrow__end">
                            <?php echo tq_badge($k, $w); ?>

                            <?php if ($l['status'] === 'pending'): ?>
                                <form method="post" action="<?php echo base_url('parent/children/link'); ?>"
                                      data-tq-confirm-title="سحب طلب ربط <?php echo html_escape($l['name']); ?>؟"
                                      data-tq-confirm="لن يصله الطلب بعد الآن، ولا يفتح شيء من بياناته — ولم يكن مفتوحا أصلا."
                                      data-tq-confirm-note="تستطيع إرسال طلب جديد إليه متى شئت."
                                      data-tq-confirm-ok="سحب الطلب">
                                    <?php echo tq_csrf(); ?>
                                    <input type="hidden" name="tq_action" value="link_cancel">
                                    <input type="hidden" name="link_id" value="<?php echo (int) $l['id']; ?>">
                                    <button class="tq-btn tq-btn--ghost tq-btn--sm" type="submit">سحب الطلب</button>
                                </form>
                            <?php elseif ($l['status'] === 'active'): ?>
                                <form method="post" action="<?php echo base_url('parent/children/link'); ?>"
                                      data-tq-confirm-title="إلغاء ربط <?php echo html_escape($l['name']); ?>؟"
                                      data-tq-confirm="لن ترى شيئا من بياناته بعدها: لا تقدمه ولا نتائجه ولا مدفوعاته."
                                      data-tq-confirm-note="يبقى في السجل تاريخ موافقته وتاريخ الإلغاء. وإعادة المتابعة تحتاج طلبا جديدا وموافقة جديدة منه."
                                      data-tq-confirm-ok="إلغاء الربط"
                                      data-tq-confirm-tone="danger">
                                    <?php echo tq_csrf(); ?>
                                    <input type="hidden" name="tq_action" value="link_revoke">
                                    <input type="hidden" name="student_id" value="<?php echo (int) $l['student_id']; ?>">
                                    <button class="tq-btn tq-btn--ghost tq-btn--sm" type="submit">إلغاء الربط</button>
                                </form>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <form method="post" action="<?php echo base_url('parent/children/link'); ?>" style="margin-block-start:var(--tq-space-xl)">
                <?php echo tq_csrf(); ?>
                <input type="hidden" name="tq_action" value="link_request">
                <div class="tq-field">
                    <label class="tq-field__label" for="tq-identifier-s">إضافة ابن — بريد حسابه في تقدر (أو رقم حسابه)</label>
                    <input class="tq-input tq-ltr" id="tq-identifier-s" name="identifier" type="text"
                           inputmode="email" required placeholder="name@example.com">
                    <p class="tq-field__hint">
                        ينشأ الطلب معلقا بلا تاريخ موافقة ولا يفتح شيئا. وتصل ابنك رسالة بنص الموافقة،
                        فإن وافق من حسابه سجل تاريخها ونسخة نصها ومن أعطاها.
                    </p>
                </div>
                <button class="tq-btn tq-btn--secondary" type="submit">إرسال طلب الربط</button>
            </form>
        </section>

        <!-- ما يصلك ومتى -->
        <section class="tq-card">
            <h2 class="tq-card__title">ما يصلك ومتى</h2>
            <p class="tq-caption">
                هذه الأحداث وحدها تقطع يومك، وما عداها ينتظر التقرير الأسبوعي.
                وهذه الشاشة تحدد أيها تريد.
            </p>

            <form method="post" action="<?php echo base_url('parent/settings/save'); ?>">
                <?php echo tq_csrf(); ?>
                <input type="hidden" name="tq_action" value="prefs_save">

                <?php
                /* `tq-prefrow` و`tq-switch` من مكتبة المكونات، لا خانات متصفح خام.
                   كانت هذه القائمة `dl.tq-s-list > .tq-s-row`، وهما صنفان
                   **غير معرفين في أي ملف CSS**: يعرفهما `tq_student_styles.php`
                   وحده، وشاشات ولي الأمر لا تضمنه. فكان كل صف هنا يعرض بلا
                   نمط: التسمية سطرا والخانة سطرا تحتها، بخط المتصفح وحجمه —
                   وهو ما تراه الشاشة اليوم. وشاشة الطالب تستعمل المفتاح نفسه
                   للسؤال نفسه، ولا سبب لاختلاف الشكل بين البوابتين.

                   والوصف تحت كل تسمية ليس زينة: «رسوب في اختبار محطة» لا يفهمه
                   من لا يعرف ما المحطة، وهو مصطلح المنصة لا مصطلح الناس. */
                $tq_hints = [
                    'exam_result'      => 'حين تعتمد درجة اختبار لابنك.',
                    'placement_result' => 'حين يؤدي اختبار تحديد المستوى — يقول لك أين موضعه '
                                        . 'وأي باقة تناسبه، ويصلك بالبريد كذلك.',
                    'station_failed'   => 'حين لا يجتاز اختبار نهاية وحدة، وهو أول ما يستحق تدخلك.',
                    'inactivity_3days' => 'حين يمر ثلاثة أيام بلا أن يفتح المنصة.',
                    'session_request'  => 'حين يطلب حصة خاصة مع معلم أو يؤكد له موعد.',
                    'certificate'      => 'حين ينهي محطة ويصدر له شهادة.',
                ];
                $tq_switch = function ($id, $name, $on) {
                    return '<span class="tq-switch">'
                         . '<input type="checkbox" id="' . html_escape($id) . '" name="' . html_escape($name) . '" value="1"'
                         . ($on ? ' checked' : '') . '>'
                         . '<span class="tq-switch__track" aria-hidden="true"></span>'
                         . '<span class="tq-switch__knob" aria-hidden="true"></span>'
                         . '</span>';
                };
                ?>

                <div class="tq-prefrow">
                    <span class="tq-prefrow__main">
                        <label class="tq-prefrow__title" for="tq-n-weekly">التقرير الأسبوعي صباح الأحد</label>
                        <span class="tq-prefrow__hint">أربعة أسطر عن كل ابن، تقرأ في عشر ثوان.</span>
                    </span>
                    <span class="tq-prefrow__end">
                        <?php echo $tq_switch('tq-n-weekly', 'notify[weekly]', !empty($tq_prefs['weekly'])); ?>
                    </span>
                </div>

                <?php foreach ($tq_types as $tq_k => $tq_label): ?>
                    <div class="tq-prefrow">
                        <span class="tq-prefrow__main">
                            <label class="tq-prefrow__title" for="tq-n-<?php echo html_escape($tq_k); ?>"><?php echo html_escape($tq_label); ?></label>
                            <?php if (isset($tq_hints[$tq_k])): ?>
                                <span class="tq-prefrow__hint"><?php echo html_escape($tq_hints[$tq_k]); ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="tq-prefrow__end">
                            <?php echo $tq_switch('tq-n-' . $tq_k, 'notify[' . $tq_k . ']', !empty($tq_prefs[$tq_k])); ?>
                        </span>
                    </div>
                <?php endforeach; ?>

                <?php $tq_active_links = array_filter($tq_links, function ($l) { return $l['status'] === 'active'; }); ?>
                <?php if ($tq_active_links): ?>
                    <h3 class="tq-strong" style="margin-block:var(--tq-space-xl) var(--tq-space-s)">أيام الدراسة المتوقعة أسبوعيا</h3>
                    <p class="tq-caption" style="margin-block-start:0">
                        عليها يقاس «ما بقي من خطة أسبوعه» في التقرير. وما لم تحددها،
                        يحسب على <?php echo TQ_LRI . Taqdar_parent_model::PLAN_DAYS_DEFAULT . TQ_PDI; ?> أيام
                        ويكتب في التقرير أنها الافتراضية لا خطتك.
                    </p>
                    <p class="tq-micro" style="margin-block-start:0">
                        وهي عن ابنك لا عنك، فتحفظ في نطاق رابطه — بخلاف خانات التنبيه فوق
                        وهي عن حسابك أنت.
                    </p>
                    <?php foreach ($tq_active_links as $l):
                            $sid  = (int) $l['student_id'];
                            $plan = $tq_pm->plan_days($tq_uid, $sid);
                        ?>
                            <div class="tq-prefrow">
                                <span class="tq-prefrow__main">
                                    <label class="tq-prefrow__title" for="tq-plan-<?php echo $sid; ?>"><?php echo html_escape($l['name']); ?></label>
                                </span>
                                <span class="tq-prefrow__end">
                                    <?php /* «غير محددة» خيار صريح وأول القائمة.
                                             بدونه ينتقي المتصفح أول خيار (يوم واحد) لمن لم
                                             يحدد خطة، فأول ضغطة على «حفظ التفضيلات» — ولو
                                             لتغيير خانة إشعار لا علاقة لها — تكتب خطة يوم
                                             واحد لكل ابن. والشاشة تقول تحته «غير محددة»
                                             بينما الحقل فوقه يقول «يوم واحد»: تناقض على
                                             سطرين متجاورين. */ ?>
                                    <select class="tq-select" id="tq-plan-<?php echo $sid; ?>" name="plan_days[<?php echo $sid; ?>]">
                                        <option value="" <?php echo $plan['is_default'] ? 'selected' : ''; ?>>
                                            غير محددة — يحسب على <?php echo TQ_LRI . Taqdar_parent_model::PLAN_DAYS_DEFAULT . TQ_PDI; ?> أيام
                                        </option>
                                        <?php for ($d = 1; $d <= 7; $d++): ?>
                                            <option value="<?php echo $d; ?>" <?php echo (!$plan['is_default'] && (int) $plan['days'] === $d) ? 'selected' : ''; ?>>
                                                <?php echo html_escape(tq_days($d)); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </span>
                            </div>
                        <?php endforeach; ?>
                <?php endif; ?>

                <button class="tq-btn tq-btn--primary" type="submit" style="margin-block-start:var(--tq-space-l)">حفظ التفضيلات</button>
                <p class="tq-micro" style="margin-block-start:var(--tq-space-m)">
                    ما توقفه هنا لا يكتب أصلا — يفرض عند إنشاء الإشعار لا عند عرضه،
                    فالموقوف لا يصلك ولا يقف في صندوقك.
                </p>
            </form>
        </section>

        <!-- حدود ما تراه -->
        <section class="tq-card">
            <h2 class="tq-card__title">حدود ما تراه</h2>
            <p class="tq-caption">
                الرقابة الكاملة تنتج طالبا يخفي، لا طالبا يتعلم. ولذلك حدود الرؤية
                معلنة لك لا مخفية عنك.
            </p>
            <div class="tq-grid tq-grid--2">
                <div class="tq-pastel tq-pastel--mint">
                    <span class="tq-pastel__label tq-micro">ترى</span>
                    <ul class="tq-pastel__body tq-stack" style="--tq-space-l:var(--tq-space-xs);margin-block-start:var(--tq-space-s)">
                        <li>الدروس المكتملة والإتقان لكل مادة</li>
                        <li>أيام النشاط والالتزام</li>
                        <li>المدفوعات والفواتير</li>
                        <li>ملاحظات المعلمين</li>
                    </ul>
                </div>
                <div class="tq-pastel tq-pastel--sand">
                    <span class="tq-pastel__label tq-micro">لا ترى</span>
                    <ul class="tq-pastel__body tq-stack" style="--tq-space-l:var(--tq-space-xs);margin-block-start:var(--tq-space-s)">
                        <li>محادثات المساعد الذكي</li>
                        <li>منشورات المجتمع</li>
                        <li>كل إجابة خاطئة على حدة</li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="tq-card">
            <div class="tq-prefrow">
                <span class="tq-prefrow__main">
                    <span class="tq-prefrow__title">تسجيل الخروج</span>
                    <span class="tq-prefrow__hint">إنهاء جلستك على هذا الجهاز.</span>
                </span>
                <span class="tq-prefrow__end">
                    <?php /* زر ينهي الجلسة بنقرة واحدة وبجواره أزرار حفظ —
                             والسهو فيه يكلف إعادة الدخول وسط عمل. */ ?>
                    <a class="tq-btn tq-btn--danger tq-btn--sm" href="<?php echo base_url('login/logout'); ?>"
                       data-tq-confirm-title="تسجيل الخروج؟"
                       data-tq-confirm="تنتهي جلستك على هذا الجهاز، وتحتاج بريدك وكلمة مرورك للدخول من جديد."
                       data-tq-confirm-ok="تسجيل الخروج"
                       data-tq-confirm-tone="danger">تسجيل الخروج</a>
                </span>
            </div>
        </section>
    </div>

    <aside class="tq-aside">
        <div class="tq-card">
            <h2 class="tq-card__title">التقرير الأسبوعي</h2>
            <p class="tq-caption">
                يصلك مرة كل أسبوع في يوم ثابت، بلغة الناس لا بجدول درجات.
                وما عدا الأحداث التي فوق، لا شيء يقاطعك قبله.
            </p>
            <a class="tq-btn tq-btn--secondary tq-btn--sm tq-btn--block" href="<?php echo base_url('parent/weekly'); ?>">
                عرض آخر تقرير
            </a>
        </div>

        <div class="tq-pastel tq-pastel--sand">
            <span class="tq-pastel__label tq-micro">الموافقة</span>
            <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                لا يفعل رابط بلا تاريخ موافقة — والقاعدة نفسها ترفض ذلك، لا الشاشة وحدها.
                والموافقة يعطيها ابنك من حسابه، أو الإدارة نيابة عند التوثيق الورقي.
            </p>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
