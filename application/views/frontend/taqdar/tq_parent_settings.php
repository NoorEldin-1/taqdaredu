<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * إعدادات وليّ الأمر.
 *
 * كانت هذه الشاشة عرضًا بلا نموذج واحد: لا تعديل بيانات، ولا تفضيلات،
 * وزرّ «تغيير كلمة المرور» يشير إلى `/profile` وهو يُرجع 500 — فالمستخدم
 * يصطدم بجدار. صارت الآن ثلاثة نماذج تكتب فعلًا عبر `Taqdar_parent_model`:
 * بياناتك · كلمة مرورك · ما يصلك ومتى.
 *
 * والربط بالابن **بموافقة موثَّقة** لا بتخمين: يُطلب من هنا (أو من شاشة
 * «أبنائي»)، ويبدأ `pending` بلا تاريخ، ولا يصير `active` إلا بموافقة الابن
 * من حسابه وبتاريخ في `consent_at` — وهو شرط مفروض في القاعدة نفسها لا في
 * الشيفرة وحدها. وحدود الرؤية معروضة صراحةً لا مخفيّة: وليّ الأمر يعرف
 * ما لا يراه، فالشفافية مع الأسرة لا تعني إلغاء مساحة الطالب.
 */

$tq_ci = &get_instance();   // الطبقةُ المُحمَّلة داخل عرضٍ لا تُنسخ على $this، فتُؤخذ من النسخة
$tq_ci->load->model('taqdar_parent_model');
$tq_pm = $tq_ci->taqdar_parent_model;
/* النماذج تنشر إلى `POST parent/settings/save` و`POST parent/children/link`
   في المتحكّم، وكلاهما يستدعي دوالّ هذا النموذج. وهذا النداء شبكة أمان
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
                    <p class="tq-field__hint">هو نفسه اسم دخولك — وتغييره يغيّر ما تدخل به.</p>
                </div>

                <div class="tq-grid tq-grid--2">
                    <div class="tq-field">
                        <label class="tq-field__label" for="tq-phone">الجوّال</label>
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
                <dl class="tq-s-list">
                    <?php foreach ($tq_links as $l):
                        $k = $l['status'] === 'active' ? 'mastered' : ($l['status'] === 'pending' ? 'due' : 'late');
                        $w = ['active' => 'مفعّل', 'pending' => 'بانتظار موافقته', 'revoked' => 'مسحوب'][$l['status']] ?? $l['status'];
                    ?>
                        <div class="tq-s-row">
                            <dt>
                                <span class="tq-strong"><?php echo html_escape($l['name']); ?></span>
                                <span class="tq-micro" style="display:block"><?php echo tq_iso(html_escape((string) $l['email'])); ?></span>
                                <span class="tq-micro" style="display:block">
                                    <?php if (!empty($l['consent_at']) && $l['status'] === 'active'): ?>
                                        وافق بتاريخ <?php echo TQ_LRI . html_escape((string) $l['consent_at']) . TQ_PDI; ?>
                                    <?php elseif ($l['status'] === 'pending'): ?>
                                        لا تاريخ موافقة بعد — لا يُفتح شيء من بياناته
                                    <?php elseif (!empty($l['prefs']['revoked']['at'])): ?>
                                        أُلغي الربط بتاريخ <?php echo TQ_LRI . html_escape((string) $l['prefs']['revoked']['at']) . TQ_PDI; ?>
                                    <?php endif; ?>
                                </span>
                            </dt>
                            <dd style="margin:0;display:flex;gap:var(--tq-space-m);align-items:center">
                                <?php echo tq_badge($k, $w); ?>

                                <?php if ($l['status'] === 'pending'): ?>
                                    <form method="post" action="<?php echo base_url('parent/children/link'); ?>"
                                          onsubmit="return confirm('سحب طلب ربط <?php echo html_escape($l['name']); ?>؟');">
                                        <input type="hidden" name="tq_action" value="link_cancel">
                                        <input type="hidden" name="link_id" value="<?php echo (int) $l['id']; ?>">
                                        <button class="tq-btn tq-btn--ghost tq-btn--sm" type="submit">سحب الطلب</button>
                                    </form>
                                <?php elseif ($l['status'] === 'active'): ?>
                                    <form method="post" action="<?php echo base_url('parent/children/link'); ?>"
                                          onsubmit="return confirm('إلغاء ربط <?php echo html_escape($l['name']); ?>؟ لن ترى شيئًا من بياناته بعدها.');">
                                        <input type="hidden" name="tq_action" value="link_revoke">
                                        <input type="hidden" name="student_id" value="<?php echo (int) $l['student_id']; ?>">
                                        <button class="tq-btn tq-btn--ghost tq-btn--sm" type="submit">إلغاء الربط</button>
                                    </form>
                                <?php endif; ?>
                            </dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>

            <form method="post" action="<?php echo base_url('parent/children/link'); ?>" style="margin-block-start:var(--tq-space-xl)">
                <input type="hidden" name="tq_action" value="link_request">
                <div class="tq-field">
                    <label class="tq-field__label" for="tq-identifier-s">إضافة ابن — بريد حسابه في تقدّر (أو رقم حسابه)</label>
                    <input class="tq-input tq-ltr" id="tq-identifier-s" name="identifier" type="text"
                           inputmode="email" required placeholder="name@example.com">
                    <p class="tq-field__hint">
                        يُنشأ الطلب معلّقًا بلا تاريخ موافقة ولا يفتح شيئًا. وتصل ابنك رسالةٌ بنصّ الموافقة،
                        فإن وافق من حسابه سُجّل تاريخها ونسخة نصّها ومن أعطاها.
                    </p>
                </div>
                <button class="tq-btn tq-btn--secondary" type="submit">إرسال طلب الربط</button>
            </form>
        </section>

        <!-- ما يصلك ومتى -->
        <section class="tq-card">
            <h2 class="tq-card__title">ما يصلك ومتى</h2>
            <p class="tq-caption">
                خمسة أحداث وحدها تقطع يومك، وما عداها ينتظر التقرير الأسبوعي.
                وهذه الشاشة تحدّد أيّها تريد.
            </p>

            <form method="post" action="<?php echo base_url('parent/settings/save'); ?>">
                <input type="hidden" name="tq_action" value="prefs_save">

                <dl class="tq-s-list">
                    <div class="tq-s-row">
                        <dt><label class="tq-strong" for="tq-n-weekly">التقرير الأسبوعي صباح الأحد</label></dt>
                        <dd style="margin:0">
                            <input type="checkbox" id="tq-n-weekly" name="notify[weekly]" value="1"
                                   <?php echo !empty($tq_prefs['weekly']) ? 'checked' : ''; ?>>
                        </dd>
                    </div>
                    <?php foreach ($tq_types as $tq_k => $tq_label): ?>
                        <div class="tq-s-row">
                            <dt><label for="tq-n-<?php echo html_escape($tq_k); ?>"><?php echo html_escape($tq_label); ?></label></dt>
                            <dd style="margin:0">
                                <input type="checkbox" id="tq-n-<?php echo html_escape($tq_k); ?>"
                                       name="notify[<?php echo html_escape($tq_k); ?>]" value="1"
                                       <?php echo !empty($tq_prefs[$tq_k]) ? 'checked' : ''; ?>>
                            </dd>
                        </div>
                    <?php endforeach; ?>
                </dl>

                <?php $tq_active_links = array_filter($tq_links, function ($l) { return $l['status'] === 'active'; }); ?>
                <?php if ($tq_active_links): ?>
                    <h3 class="tq-strong" style="margin-block:var(--tq-space-xl) var(--tq-space-s)">أيام الدراسة المتوقَّعة أسبوعيًّا</h3>
                    <p class="tq-caption" style="margin-block-start:0">
                        عليها يُقاس «ما بقي من خطة أسبوعه» في التقرير. وما لم تحدّدها،
                        يُحسب على <?php echo TQ_LRI . Taqdar_parent_model::PLAN_DAYS_DEFAULT . TQ_PDI; ?> أيام
                        ويُكتب في التقرير أنها الافتراضية لا خطّتك.
                    </p>
                    <dl class="tq-s-list">
                        <?php foreach ($tq_active_links as $l):
                            $sid  = (int) $l['student_id'];
                            $plan = $tq_pm->plan_days($tq_uid, $sid);
                        ?>
                            <div class="tq-s-row">
                                <dt><label for="tq-plan-<?php echo $sid; ?>"><?php echo html_escape($l['name']); ?></label></dt>
                                <dd style="margin:0">
                                    <select class="tq-select" id="tq-plan-<?php echo $sid; ?>" name="plan_days[<?php echo $sid; ?>]">
                                        <?php for ($d = 1; $d <= 7; $d++): ?>
                                            <option value="<?php echo $d; ?>" <?php echo (!$plan['is_default'] && $plan['days'] === $d) ? 'selected' : ''; ?>>
                                                <?php echo TQ_LRI . $d . TQ_PDI; ?> أيام
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                    <?php if ($plan['is_default']): ?>
                                        <span class="tq-micro" style="display:block">غير محدَّدة — يُحسب على الافتراضي</span>
                                    <?php endif; ?>
                                </dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                <?php endif; ?>

                <button class="tq-btn tq-btn--primary" type="submit" style="margin-block-start:var(--tq-space-l)">حفظ التفضيلات</button>
                <p class="tq-micro" style="margin-block-start:var(--tq-space-m)">
                    تُحفظ في نطاق الرابط نفسه (<span class="tq-ltr">parent_links.scope</span>)،
                    فهي جزء من «ما يصلك عن هذا الابن» لا إعدادًا معلّقًا في الهواء.
                </p>
            </form>
        </section>

        <!-- حدود ما تراه -->
        <section class="tq-card">
            <h2 class="tq-card__title">حدود ما تراه</h2>
            <p class="tq-caption">
                الرقابة الكاملة تُنتج طالبًا يُخفي، لا طالبًا يتعلّم. ولذلك حدود الرؤية
                معلَنة لك لا مخفيّة عنك.
            </p>
            <div class="tq-grid tq-grid--2">
                <div class="tq-pastel tq-pastel--mint">
                    <span class="tq-pastel__label tq-micro">ترى</span>
                    <ul class="tq-pastel__body tq-stack" style="--tq-space-l:var(--tq-space-xs);margin-block-start:var(--tq-space-s)">
                        <li>الدروس المكتملة والإتقان لكل مادة</li>
                        <li>أيام النشاط والالتزام</li>
                        <li>المدفوعات والفواتير</li>
                        <li>ملاحظات المعلّمين</li>
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
            <div class="tq-s-row">
                <div>
                    <p class="tq-strong" style="margin:0">تسجيل الخروج</p>
                    <p class="tq-micro" style="margin:0">إنهاء جلستك على هذا الجهاز.</p>
                </div>
                <a class="tq-btn tq-btn--danger tq-btn--sm" href="<?php echo base_url('login/logout'); ?>">تسجيل الخروج</a>
            </div>
        </section>
    </div>

    <aside class="tq-aside">
        <div class="tq-card">
            <h2 class="tq-card__title">التقرير الأسبوعي</h2>
            <p class="tq-caption">
                يصلك مرّة كل أسبوع في يوم ثابت، بلغة الناس لا بجدول درجات.
                وما عدا الأحداث الخمسة، لا شيء يقاطعك قبله.
            </p>
            <a class="tq-btn tq-btn--secondary tq-btn--sm tq-btn--block" href="<?php echo base_url('parent/weekly'); ?>">
                عرض آخر تقرير
            </a>
        </div>

        <div class="tq-pastel tq-pastel--sand">
            <span class="tq-pastel__label tq-micro">الموافقة</span>
            <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                لا يُفعَّل رابط بلا تاريخ موافقة — والقاعدة نفسها ترفض ذلك، لا الشاشة وحدها.
                والموافقة يعطيها ابنك من حسابه، أو الإدارة نيابةً عند التوثيق الورقي.
            </p>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
