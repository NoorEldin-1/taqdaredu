<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * إعدادات البريد الصادر.
 *
 * كلمة المرور **لا تعاد إلى الصفحة أبدا**: تعرض نائبة، ويترك الحقل
 * فارغا للإبقاء عليها. فحقل يحمل السر يتسرب في مصدر الصفحة وفي أي
 * لقطة شاشة، والفائدة منه صفر — لا أحد يقرأ كلمة مروره من نموذج.
 */
$M   = $mail;
$has = !empty($M['smtp_pass']);
?>

<div class="tqa-head">
    <div>
        <h1>البريد الصادر</h1>
        <p>منه ترسل استعادة كلمة المرور والتقرير الأسبوعي وتنبيهات أولياء الأمور.</p>
    </div>
</div>

<?php tqa_flash(); ?>

<?php if (!$configured): ?>
    <div class="tqa-block">
        <h3>البريد غير مضبوط</h3>
        <p>
            ثلاث ميزات موقوفة عليه ولا تعمل بدونه: <strong>استعادة كلمة المرور</strong>
            (فمن ينسى كلمته لا يستطيع الرجوع إلى حسابه)، و<strong>التقرير الأسبوعي</strong>
            لولي الأمر، و<strong>التنبيهات البريدية</strong>.
        </p>
    </div>
<?php else: ?>
    <div class="tqa-note">
        <strong>البريد مضبوط.</strong>
        ويبقى الضبط ادعاء حتى تنجح رسالة فحص فعلية — الزر أسفل الصفحة.
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h4 class="header-title">بيانات الخادم</h4></div>
            <div class="card-body">
                <form method="post" action="<?php echo site_url('taqdar_admin/mail_save'); ?>">
                    <div class="row">
                        <div class="col-md-6 tqa-field">
                            <label for="m_host">خادم البريد <span class="tqa-req">*</span></label>
                            <input class="form-control tq-ltr" dir="ltr" type="text" id="m_host" name="smtp_host"
                                   value="<?php echo html_escape($M['smtp_host']); ?>" required>
                            <small class="tqa-hint">لبريد النطاق على Hostinger: <span class="tq-ltr" dir="ltr">smtp.hostinger.com</span></small>
                        </div>

                        <div class="col-md-3 tqa-field">
                            <label for="m_port">المنفذ <span class="tqa-req">*</span></label>
                            <input class="form-control tq-ltr" dir="ltr" type="number" id="m_port" name="smtp_port"
                                   value="<?php echo html_escape($M['smtp_port']); ?>" required>
                            <small class="tqa-hint">465 مع SSL · 587 مع TLS</small>
                        </div>

                        <div class="col-md-3 tqa-field">
                            <label for="m_crypto">التعمية</label>
                            <select class="form-control" id="m_crypto" name="smtp_crypto">
                                <?php foreach (array('ssl' => 'SSL', 'tls' => 'TLS', '' => 'بلا تعمية') as $k => $lbl): ?>
                                    <option value="<?php echo $k; ?>" <?php echo ((string) $M['smtp_crypto'] === (string) $k) ? 'selected' : ''; ?>>
                                        <?php echo $lbl; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 tqa-field">
                            <label for="m_user">اسم المستخدم <span class="tqa-req">*</span></label>
                            <input class="form-control tq-ltr" dir="ltr" type="text" id="m_user" name="smtp_user"
                                   value="<?php echo html_escape($M['smtp_user']); ?>" required>
                            <small class="tqa-hint">عنوان الصندوق كاملا، مثل <span class="tq-ltr" dir="ltr">info@taqdaredu.com</span></small>
                        </div>

                        <div class="col-md-6 tqa-field">
                            <label for="m_pass">كلمة المرور <?php echo $has ? '' : '<span class="tqa-req">*</span>'; ?></label>
                            <input class="form-control tq-ltr" dir="ltr" type="password" id="m_pass" name="smtp_pass"
                                   autocomplete="new-password"
                                   placeholder="<?php echo $has ? 'محفوظة — اتركه فارغا للإبقاء عليها' : 'كلمة مرور الصندوق'; ?>">
                            <small class="tqa-hint">لا تعرض المحفوظة هنا أبدا؛ اكتب قيمة جديدة لتبديلها فقط.</small>
                        </div>

                        <div class="col-md-6 tqa-field">
                            <label for="m_from">المرسل الظاهر <span class="tqa-req">*</span></label>
                            <input class="form-control tq-ltr" dir="ltr" type="email" id="m_from" name="smtp_from_email"
                                   value="<?php echo html_escape($M['smtp_from_email']); ?>" required>
                            <small class="tqa-hint">يستحسن أن يطابق اسم المستخدم، وإلا رفضته كثير من الخوادم.</small>
                        </div>

                        <div class="col-md-6 tqa-field">
                            <label for="m_sys">بريد النظام</label>
                            <input class="form-control tq-ltr" dir="ltr" type="email" id="m_sys" name="system_email"
                                   value="<?php echo html_escape($M['system_email']); ?>">
                            <small class="tqa-hint">إليه تصل رسائل التواصل وتنبيهات النظام.</small>
                        </div>
                    </div>

                    <div class="tqa-actions">
                        <button type="submit" class="btn btn-primary">حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h4 class="header-title">رسالة فحص</h4></div>
            <div class="card-body">
                <p class="tqa-hint" style="margin-block-end:var(--tq-space-l)">
                    ترسل رسالة حقيقية بالإعدادات المحفوظة. وإن فشلت يعرض
                    <strong>سبب الفشل كما قاله الخادم</strong> لا رسالة عامة —
                    فالسبب هو ما يصلح، لا الفشل.
                </p>

                <form method="post" action="<?php echo site_url('taqdar_admin/mail_test'); ?>">
                    <div class="tqa-field">
                        <label for="m_to">أرسل إلى</label>
                        <input class="form-control tq-ltr" dir="ltr" type="email" id="m_to" name="to"
                               value="<?php echo html_escape($M['smtp_from_email']); ?>" required>
                    </div>
                    <button type="submit" class="btn btn-success" <?php echo $configured ? '' : 'disabled'; ?>>
                        أرسل رسالة فحص
                    </button>
                    <?php if (!$configured): ?>
                        <small class="tqa-warn">احفظ البيانات أولا.</small>
                    <?php endif; ?>
                </form>

                <?php if (!empty($debug)): ?>
                    <div style="margin-block-start:var(--tq-space-l)">
                        <strong class="tqa-hint">ما قاله الخادم:</strong>
                        <pre class="tqa-debug tq-ltr" dir="ltr"><?php echo html_escape($debug); ?></pre>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h4 class="header-title">ما يعتمد على البريد</h4></div>
            <div class="card-body">
                <ul class="tqa-deps">
                    <li>استعادة كلمة المرور <span class="badge badge-<?php echo $configured ? 'success' : 'danger'; ?>"><?php echo $configured ? 'تعمل' : 'معطلة'; ?></span></li>
                    <li>التقرير الأسبوعي لولي الأمر <span class="badge badge-<?php echo $configured ? 'success' : 'danger'; ?>"><?php echo $configured ? 'تعمل' : 'معطلة'; ?></span></li>
                    <li>التنبيهات البريدية
                        <span class="badge badge-<?php echo $events_email ? 'success' : 'warning'; ?>">
                            <?php echo $events_email ? 'مفعلة' : 'مطفأة'; ?>
                        </span>
                    </li>
                </ul>

                <form method="post" action="<?php echo site_url('taqdar_admin/mail_toggle_events'); ?>">
                    <button type="submit" class="btn btn-secondary btn-sm" <?php echo $configured ? '' : 'disabled'; ?>>
                        <?php echo $events_email ? 'أوقف التنبيهات البريدية' : 'فعل التنبيهات البريدية'; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
