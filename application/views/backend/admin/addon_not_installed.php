<?php
/**
 * شاشة حالة فارغة مفهومة: تُعرَض حين تُطلب ميزة تعتمد على إضافة غير مثبّتة
 * (مثل إضافة الذكاء الاصطناعي OpenAI). تُغني عن الخطأ القاتل «Unable to load model».
 */
$tq_label = isset($tq_addon_label) && $tq_addon_label !== '' ? $tq_addon_label : '';
?>
<div class="row justify-content-center">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-body text-center" style="padding: 48px 24px;">
                <i class="mdi mdi-puzzle-outline" style="font-size: 56px; color: #b0b0b0;"></i>
                <h4 class="mt-3 mb-2">هذه الميزة تعتمد على إضافة غير مثبّتة</h4>
                <p class="text-muted mb-1">
                    الإضافة المطلوبة<?php echo $tq_label ? ' (<strong>' . html_escape($tq_label) . '</strong>)' : ''; ?>
                    غير موجودة على هذا النظام، ولذلك لا يمكن فتح هذه الشاشة.
                </p>
                <p class="text-muted mb-4">
                    لا يوجد عطب في الموقع؛ يكفي تثبيت الإضافة من قسم الإضافات لتفعيل هذه الميزة.
                </p>
                <a href="<?php echo site_url('admin/available_addon'); ?>" class="btn btn-primary">
                    <i class="mdi mdi-view-grid-plus-outline"></i> الذهاب إلى الإضافات
                </a>
                <a href="<?php echo site_url('admin/dashboard'); ?>" class="btn btn-outline-secondary ml-2">
                    العودة إلى اللوحة
                </a>
            </div>
        </div>
    </div>
</div>
