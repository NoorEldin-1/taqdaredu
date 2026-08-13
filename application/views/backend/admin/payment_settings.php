<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * بوابات الدفع.
 *
 * أعيدت كتابتها بهيكل `tqa-*`. وما تغير:
 *
 * ١ — **«نعم/لا» في منتقيين صارا مفتاحين.** التفعيل ووضع الاختبار قيمتان
 *     ثنائيتان، والمنتقي لهما يحتاج ضغطتين ليقرأ ما يقرؤه المفتاح بنظرة.
 * ٢ — **`required` على كل مفتاح بوابة.** بوابة معطلة لا تستعمل مفاتيحها،
 *     ومع ذلك كان النموذج يرفض الحفظ حتى تملأ **كل** حقولها — فمن أراد
 *     أن يعطل PayPal لم يستطع أن يحفظ حتى يخترع له مفاتيح. صار الإلزام
 *     مقصورا على المفعلة.
 * ٣ — **وضع الاختبار لم يكن يعلن أثره.** بوابة مفعلة في وضع الاختبار
 *     تقبل الدفع ولا تحصل مالا، وهي أخطر حالة في هذه الشاشة كلها —
 *     فصارت تعلن بشارة حمراء في رأس البطاقة.
 * ٤ — **العملات كانت تجلب داخل الحلقة** — `get_currencies()` مرة لكل
 *     بوابة. صارت مرة واحدة.
 * ٥ — حذف الشرط `addon_status(...)`: كاذب أبدا في هذا التركيب، وهو ما
 *     كان يسقط بوابات «الإضافات» كلها من العرض بلا خبر.
 *
 * و«الدفع اليدوي» (`offline_payment`) يبقى مستثنى كما كان: بياناته تحرر
 * من شاشة [tqa_bank.php] لا من هنا.
 */
$tq_currencies = $this->crud_model->get_currencies();
$tq_sys        = (string) get_settings('system_currency');
$tq_pos        = (string) get_settings('currency_position');

$tq_positions = array(
    'left'        => 'قبل الرقم',
    'right'       => 'بعد الرقم',
    'left-space'  => 'قبل الرقم بمسافة',
    'right-space' => 'بعد الرقم بمسافة',
);

/** أسماء المفاتيح — كانت تعرض `get_phrase('secret_key')` فتخرج بالإنجليزية. */
$tq_keynames = array(
    'client_id'      => 'معرف العميل',
    'client_secret'  => 'سر العميل',
    'secret_key'     => 'المفتاح السري',
    'publishable_key' => 'المفتاح المنشور',
    'public_key'     => 'المفتاح العام',
    'private_key'    => 'المفتاح الخاص',
    'api_key'        => 'مفتاح الواجهة',
    'merchant_id'    => 'معرف التاجر',
    'merchant_key'   => 'مفتاح التاجر',
    'salt'           => 'الملح',
    'theme_color'    => 'لون الواجهة',
    'pos_id'         => 'معرف نقطة البيع',
    'md5'            => 'بصمة MD5',
);
?>

<?php tqa_head('إعدادات الدفع الموروثة',
    'عملة النظام، وبوابات Academy الست عشرة. والدفع في تقدر لا يمر بأي منها.',
    'cog',
    '<a class="tqa-btn tqa-btn--primary" href="' . site_url('taqdar_admin/tap') . '">'
  . tq_icon('card', 16) . ' بوابة الدفع بالبطاقة</a>'
  . ' <a class="tqa-btn tqa-btn--ghost" href="' . site_url('taqdar_admin/bank') . '">'
  . tq_icon('bank', 16) . ' بيانات التحويل البنكي</a>'); ?>

<?php /* TQ-PAY-TRAP — هذه الشاشة كانت أول ما يفتحه من يريد «ضبط الدفع»،
         وتعرض ست عشرة بوابة كلها `status = 1` — فيقرؤها المسؤول فيظن أن
         PayPal وStripe وRazorpay مفعلة ويعمل بها. **ولا واحدة منها تمس
         اشتراكات تقدر:** `Taqdar_billing_model` لا يقرأ `payment_gateways`
         في سطر واحد، والبوابات هنا تخدم شراء كورسات Academy وحده — وهو
         مسار لا يبيع على هذا الموقع.
         فالحقيقة تقال في أول الشاشة لا تكتشف بعد أسبوع من انتظار دفعة. */ ?>
<div class="tqa-note tqa-note--warn tqa-section">
    <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
    <span>
        <strong>البوابات أدناه لا تحصل ثمن اشتراك واحد.</strong>
        هي بوابات قالب Academy لشراء الكورسات المفردة — ومحرك الفوترة في تقدر لا
        يقرأ هذا الجدول أصلا. وحال «مفعلة» هنا لا تعني أن الطالب يستطيع الدفع بها.
        <br>
        الدفع الفعلي في تقدر طريقان: <a href="<?php echo site_url('taqdar_admin/tap'); ?>">الدفع بالبطاقة (تاب)</a>
        و<a href="<?php echo site_url('taqdar_admin/bank'); ?>">التحويل البنكي</a>.
        وما يبقى نافعا في هذه الشاشة هو <strong>عملة النظام</strong> وحدها — وهي
        العملة التي تحصل بها البطاقة.
    </span>
</div>

<div class="tqa-cols">

    <div class="tqa-stack">

        <form class="tqa-card" method="post" enctype="multipart/form-data"
              action="<?php echo site_url('admin/payment_settings/system_currency'); ?>">
            <?php echo tq_csrf(); ?>

            <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
                <span class="tqa-iconbox tqa-mint" aria-hidden="true"><?php echo tq_icon('money', 20); ?></span>
                <h2>عملة النظام</h2>
            </div>

            <div class="tqa-fieldgrid">
                <div class="tqa-field">
                    <label class="tqa-field__label" for="system_currency">
                        العملة <span class="tqa-field__req" aria-hidden="true">*</span>
                    </label>
                    <select class="tqa-select" id="system_currency" name="system_currency" required>
                        <option value="">— اختر العملة</option>
                        <?php foreach ($tq_currencies as $tq_c): ?>
                            <option value="<?php echo html_escape($tq_c['code']); ?>"
                                <?php echo $tq_sys === $tq_c['code'] ? 'selected' : ''; ?>>
                                <?php echo html_escape($tq_c['code']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="tqa-field">
                    <label class="tqa-field__label" for="currency_position">موضع رمز العملة</label>
                    <select class="tqa-select" id="currency_position" name="currency_position" required>
                        <?php foreach ($tq_positions as $tq_k => $tq_l): ?>
                            <option value="<?php echo $tq_k; ?>" <?php echo $tq_pos === $tq_k ? 'selected' : ''; ?>>
                                <?php echo html_escape($tq_l); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="tqa-actions">
                <button type="submit" class="tqa-btn tqa-btn--primary">
                    <?php echo tq_icon('check', 16); ?> احفظ عملة النظام
                </button>
            </div>
        </form>

        <?php foreach ($payment_gateways as $tq_g):
            if ($tq_g['identifier'] === 'offline_payment') continue;

            $tq_on   = (int) $tq_g['status'] === 1;
            $tq_test = (int) $tq_g['enabled_test_mode'] === 1;
            $tq_keys = json_decode((string) $tq_g['keys'], true);
            if (!is_array($tq_keys)) $tq_keys = array();
            $tq_id   = $tq_g['identifier'];
            $tq_bad  = $tq_on && $tq_sys !== '' && $tq_g['currency'] !== $tq_sys;
        ?>
            <form class="tqa-card" method="post" enctype="multipart/form-data"
                  action="<?php echo site_url('admin/payment_settings'); ?>">
                <?php echo tq_csrf(); ?>
                <input type="hidden" name="identifier" value="<?php echo html_escape($tq_id); ?>">

                <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
                    <span class="tqa-iconbox <?php echo $tq_on ? 'tqa-mint' : ''; ?>" aria-hidden="true">
                        <?php echo tq_icon('card', 20); ?>
                    </span>
                    <h2><?php echo html_escape($tq_g['title']); ?></h2>

                    <span class="tqa-row" style="margin-inline-start:auto;gap:var(--tq-space-xs)">
                        <span class="tqa-badge tqa-badge--<?php echo $tq_on ? 'ok' : 'muted'; ?>">
                            <?php echo $tq_on ? 'مفعلة' : 'معطلة'; ?>
                        </span>
                        <?php if ($tq_on && $tq_test): ?>
                            <span class="tqa-badge tqa-badge--danger">وضع اختبار — لا يحصل مالا</span>
                        <?php endif; ?>
                    </span>
                </div>

                <?php if ($tq_bad): ?>
                    <div class="tqa-note tqa-note--warn tqa-section">
                        <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
                        <span>
                            عملة هذه البوابة
                            (<span class="tq-ltr" dir="ltr"><?php echo html_escape($tq_g['currency']); ?></span>)
                            تخالف عملة النظام
                            (<span class="tq-ltr" dir="ltr"><?php echo html_escape($tq_sys); ?></span>) —
                            فالمبلغ يحصل بعملة غير التي عرضت على الطالب.
                        </span>
                    </div>
                <?php endif; ?>

                <div class="tqa-prefrow">
                    <div class="tqa-prefrow__main">
                        <label class="tqa-prefrow__title" for="st-<?php echo $tq_id; ?>">تفعيل البوابة</label>
                        <span class="tqa-prefrow__hint">المعطلة لا تظهر للطالب في شاشة الدفع.</span>
                    </div>
                    <div class="tqa-prefrow__end">
                        <?php /* الحقل المخفي يسبق المفتاح: مربع الاختيار غير
                                 المحدد لا يرسل شيئا، فبدونه يبقى ما كان. */ ?>
                        <input type="hidden" name="status" value="0">
                        <span class="tqa-switch">
                            <input type="checkbox" id="st-<?php echo $tq_id; ?>" name="status" value="1"
                                   <?php echo $tq_on ? 'checked' : ''; ?>>
                            <span class="tqa-switch__track" aria-hidden="true"></span>
                        </span>
                    </div>
                </div>

                <div class="tqa-prefrow">
                    <div class="tqa-prefrow__main">
                        <label class="tqa-prefrow__title" for="tm-<?php echo $tq_id; ?>">وضع الاختبار</label>
                        <span class="tqa-prefrow__hint">
                            الدفع ينجح ظاهريا ولا يصل مال. أطفئه قبل الإطلاق.
                        </span>
                    </div>
                    <div class="tqa-prefrow__end">
                        <input type="hidden" name="enabled_test_mode" value="0">
                        <span class="tqa-switch">
                            <input type="checkbox" id="tm-<?php echo $tq_id; ?>" name="enabled_test_mode" value="1"
                                   <?php echo $tq_test ? 'checked' : ''; ?>>
                            <span class="tqa-switch__track" aria-hidden="true"></span>
                        </span>
                    </div>
                </div>

                <div class="tqa-fieldgrid" style="margin-block-start:var(--tq-space-l)">
                    <div class="tqa-field">
                        <label class="tqa-field__label" for="cur-<?php echo $tq_id; ?>">
                            عملة البوابة <span class="tqa-field__req" aria-hidden="true">*</span>
                        </label>
                        <select class="tqa-select" id="cur-<?php echo $tq_id; ?>" name="currency" required>
                            <option value="">— اختر العملة</option>
                            <?php foreach ($tq_currencies as $tq_c): ?>
                                <option value="<?php echo html_escape($tq_c['code']); ?>"
                                    <?php echo $tq_g['currency'] === $tq_c['code'] ? 'selected' : ''; ?>>
                                    <?php echo html_escape($tq_c['code']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php foreach ($tq_keys as $tq_k => $tq_v):
                        $tq_label = $tq_keynames[$tq_k] ?? str_replace('_', ' ', $tq_k);
                        $tq_dom   = $tq_id . '-' . $tq_k;
                    ?>
                        <?php if ($tq_k === 'theme_color'): ?>
                            <div class="tqa-field">
                                <label class="tqa-field__label" for="k-<?php echo $tq_dom; ?>">
                                    <?php echo html_escape($tq_label); ?>
                                </label>
                                <input class="tqa-input" type="color" id="k-<?php echo $tq_dom; ?>"
                                       name="<?php echo html_escape($tq_k); ?>"
                                       value="<?php echo html_escape($tq_v ?: '#023331'); ?>"
                                       style="padding:var(--tq-space-xs);block-size:var(--tqa-field-h)">
                            </div>
                        <?php else: ?>
                            <div class="tqa-field">
                                <label class="tqa-field__label" for="k-<?php echo $tq_dom; ?>">
                                    <?php echo html_escape($tq_label); ?>
                                    <?php if ($tq_on): ?><span class="tqa-field__req" aria-hidden="true">*</span><?php endif; ?>
                                </label>
                                <input class="tqa-input tqa-input--ltr" type="text" id="k-<?php echo $tq_dom; ?>"
                                       name="<?php echo html_escape($tq_k); ?>" dir="ltr"
                                       autocomplete="off" spellcheck="false"
                                       value="<?php echo html_escape($tq_v); ?>"
                                       <?php echo $tq_on ? 'required' : ''; ?>>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <div class="tqa-actions">
                    <button type="submit" class="tqa-btn tqa-btn--primary">
                        <?php echo tq_icon('check', 16); ?> احفظ <?php echo html_escape($tq_g['title']); ?>
                    </button>
                </div>
            </form>
        <?php endforeach; ?>
    </div>

    <aside>
        <div class="tqa-note">
            <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
            <span>
                <strong>عملة واحدة لا اثنتان.</strong>
                عملة النظام وعملات البوابات المفعلة يجب أن تتطابق. واختلافها لا يظهر خطأ عند
                الحفظ — يظهر في المبلغ الذي يخصم من الطالب.
            </span>
        </div>

        <div class="tqa-note tqa-note--warn" style="margin-block-start:var(--tq-space-l)">
            <span aria-hidden="true"><?php echo tq_icon('shield', 18); ?></span>
            <span>
                المفاتيح تحفظ في قاعدة البيانات لا في الشيفرة. ومن يصل إلى هذه الشاشة يصل إليها،
                فاقصر صلاحية «إعدادات النظام» على من يحتاجها.
            </span>
        </div>
    </aside>
</div>
