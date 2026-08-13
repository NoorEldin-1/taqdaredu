<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * صلاحيات مسؤول.
 *
 * الوحدات التي تمنح لها الصلاحيات. نظفت من مفاتيح وحدات حذفت (`addon` ·
 * `theme` · `coupon` · `academy_cloud` · `data_center`): مفتاح لوحدة غير
 * موجودة يعرض مفتاحا يمنح صلاحية على لا شيء، فيقرأ من يضبط الصلاحيات
 * أنه منع شيئا وهو لم يمنع.
 *
 * وملاحظة على النظام كله: جدول `permissions` فارغ الآن، و`has_permission()`
 * ترجع `true` لمن لا صف له — أي أن **كل مسؤول يرى كل شيء حتى يضبط له صف
 * هنا**. وهذا سلوك القالب الأصلي، وهو معلن في الشاشة نفسها الآن لا في
 * تعليق يقرؤه المبرمج وحده.
 *
 * وما تغير في العرض: كان `data-switch="bool"` — مفتاح من قالب Hyper يرسم
 * بـ`<label>` مجاور، ولا يظهر إطلاقا بلا ورقة أنماط القالب. صار
 * `.tqa-switch` المبني هنا، وهو المفتاح نفسه المستعمل في إعدادات البوابات.
 */
$tq_modules = array(
    'course'     => array('الكورسات',           'الإضافة والتحرير والنشر والحذف',        'book'),
    'category'   => array('أقسام الكورسات',     'شجرة الأقسام والأقسام الفرعية',         'grid'),
    'user'       => array('الحسابات',           'كل حسابات المنصة',                      'users'),
    'instructor' => array('المعلمون',           'الطلبات والملفات والإسناد',             'graduation'),
    'student'    => array('الطلاب',             'ملفات الطلاب وتقدمهم',                  'users'),
    'enrolment'  => array('التسجيل في الكورسات', 'تسجيل الطلاب وسجل التسجيل',            'clipboard'),
    'revenue'    => array('الإيرادات',          'المدفوعات والفواتير وطلبات السحب',      'wallet'),
    'messaging'  => array('الرسائل',            'صندوق رسائل الإدارة',                   'chat'),
    'blog'       => array('المدونة',            'المقالات وأقسامها',                     'file'),
    'newsletter' => array('النشرة البريدية',    'المشتركون والإرسال',                    'send'),
    'contact'    => array('رسائل التواصل',      'ما يرسل من صفحة «تواصل معنا»',          'mail'),
    'admin'      => array('المسؤولون',          'إضافة المسؤولين وضبط صلاحياتهم',        'shield'),
    'settings'   => array('إعدادات النظام',     'الموقع والدفع والبريد وتحسين البحث',    'cog'),
    'taqdar'     => array('وحدات تقدر',         'المسارات والمحطات والإتقان والاشتراكات', 'target'),
);

$tq_uid  = (int) $permission_assign_to['id'];
$tq_name = trim($permission_assign_to['first_name'] . ' ' . $permission_assign_to['last_name']);
if ($tq_name === '') $tq_name = $permission_assign_to['email'];
?>

<?php tqa_head('الصلاحيات', $tq_name, 'key',
    '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('admin/admins') . '">'
  . tq_icon('chev-prev', 16) . ' كل المسؤولين</a>'); ?>

<div class="tqa-note tqa-section">
    <span aria-hidden="true"><?php echo tq_icon('shield', 18); ?></span>
    <span>
        جدول الصلاحيات فارغ في هذه القاعدة، ومن لا صف له فيه <strong>يرى كل شيء</strong>.
        فأول مفتاح تطفئه هنا هو ما يبدأ التقييد فعلا — قبله لا فرق بين مسؤول ومسؤول.
    </span>
</div>

<div class="tqa-card tqa-card--flush" style="max-inline-size:820px">
    <div class="tqa-card__head">
        <span class="tqa-iconbox" aria-hidden="true"><?php echo tq_icon('key', 20); ?></span>
        <h2>ما يستطيع <?php echo html_escape($tq_name); ?> الوصول إليه</h2>
    </div>

    <div class="tqa-card__body">
        <?php foreach ($tq_modules as $tq_key => [$tq_label, $tq_desc, $tq_icon]):
            $tq_on = has_permission($tq_key, $tq_uid);
            $tq_dom = $tq_uid . '-' . $tq_key;
        ?>
            <div class="tqa-prefrow">
                <span class="tqa-iconbox tqa-mint" aria-hidden="true" style="inline-size:36px;block-size:36px">
                    <?php echo tq_icon($tq_icon, 18); ?>
                </span>

                <div class="tqa-prefrow__main">
                    <label class="tqa-prefrow__title" for="perm-<?php echo html_escape($tq_dom); ?>">
                        <?php echo html_escape($tq_label); ?>
                    </label>
                    <span class="tqa-prefrow__hint"><?php echo html_escape($tq_desc); ?></span>
                </div>

                <div class="tqa-prefrow__end">
                    <?php /* المفتاح يحفظ بنفسه عند التبديل — لا زر «احفظ»
                             لأربعة عشر مفتاحا. والحالة تعلن نصا لقارئ
                             الشاشة، فاللون وحده لا يحمل معنى. */ ?>
                    <span class="tqa-switch">
                        <input type="checkbox" id="perm-<?php echo html_escape($tq_dom); ?>"
                               data-tqa-perm="<?php echo html_escape($tq_dom); ?>"
                               <?php echo $tq_on ? 'checked' : ''; ?>>
                        <span class="tqa-switch__track" aria-hidden="true"></span>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
/**
 * تبديل صلاحية.
 *
 * كان النداء يرسل بـ`$.ajax` ثم يعرض `$.NotificationApp.send(...)`
 * **في كل حال** — بما فيها حال الفشل: المعالج `success` وحده مكتوب،
 * فرد 403 أو 500 يمر بلا كلمة، ويقرأ المسؤول «حدثت الصلاحية» وهي لم
 * تتغير. وهنا يعاد المفتاح إلى وضعه إن لم يرد الخادم بنجاح.
 */
(function () {
    'use strict';

    var URL  = <?php echo json_encode(site_url('admin/assign_permission')); ?>;
    var CSRF = window.TQ_CSRF || null;

    Array.prototype.forEach.call(document.querySelectorAll('[data-tqa-perm]'), function (box) {
        box.addEventListener('change', function () {
            var was  = !box.checked;
            var body = new URLSearchParams();
            body.set('arg', box.getAttribute('data-tqa-perm'));
            if (CSRF && CSRF.name) body.set(CSRF.name, CSRF.hash);

            box.disabled = true;

            fetch(URL, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            }).then(function (r) {
                if (!r.ok) throw new Error(r.status);
                if (window.TQA) TQA.ok(box.checked ? 'منحت الصلاحية' : 'سحبت الصلاحية');
            }).catch(function () {
                box.checked = was;
                if (window.TQA) TQA.error('لم تحفظ الصلاحية. حدث الصفحة وأعد المحاولة.');
            }).then(function () {
                box.disabled = false;
            });
        });
    });
})();
</script>
