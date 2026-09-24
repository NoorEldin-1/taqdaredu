<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<style>
.tq-assess{max-width:900px;margin:auto;padding:48px 20px 64px;color:#003c37;direction:rtl}
.tq-assess__card{background:#fcf8f2;border:1px solid #eadbc5;border-radius:24px;padding:clamp(20px,4vw,40px)}
.tq-assess h1{font-size:clamp(26px,5vw,40px);line-height:1.5;margin:0 0 16px}
.tq-assess p,.tq-assess li{line-height:1.9}.tq-assess ol{padding-inline-start:24px}
.tq-assess__grades{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:24px 0}
.tq-assess__grades a{display:block;border:1px solid #007f71;background:white;border-radius:12px;padding:14px;text-align:center;color:#003c37;font-weight:700;text-decoration:none}
.tq-assess__grades a:focus-visible{outline:3px solid #b88b43;outline-offset:3px}
.tq-assess__note{font-size:14px;color:#52676d}.tq-assess__paid{border-top:1px solid #eadbc5;padding-top:20px;margin-top:24px}
@media(max-width:420px){.tq-assess__grades{grid-template-columns:1fr}.tq-assess{padding:28px 14px 40px}}
</style>
<main class="tq-assess"><section class="tq-assess__card" aria-labelledby="assessment-title">
<p>اعرف نقطة البداية، ثم اختر ما يناسبك</p>
<h1 id="assessment-title">اختبار تحديد المستوى المجاني</h1>
<p>اختبار تعليمي يساعد الطالب على معرفة مستواه في الصفوف المتاحة أدناه. لا يحتاج إلى بطاقة دفع، ولا يشتري اشتراكًا تلقائيًا.</p>
<ol><li>اختر الصف الدراسي المتاح.</li><li>أنشئ حساب الطالب أو سجّل الدخول إلى حسابه، وتأكد من صحة صفّه.</li><li>أكمل الاختبار من حساب الطالب وشاهد نتيجته والتوصية المناسبة.</li></ol>
<?php if ($assessment_grades): ?>
<h2>اختر صف الطالب</h2>
<div class="tq-assess__grades">
<?php foreach ($assessment_grades as $exam):
    $target = $this->session->userdata('user_id')
        ? base_url('student/placement')
        : base_url('sign_up') . '?next=' . rawurlencode('student/placement') . '&grade_id=' . (int) $exam['grade_id']; ?>
<a data-assessment-entry href="<?php echo html_escape($target); ?>"><?php echo html_escape($exam['grade_name']); ?></a>
<?php endforeach; ?>
</div>
<?php else: ?><p>نعمل على تجهيز الاختبارات المتاحة. يمكنك حاليًا استكشاف الباقات، والشراء اختياري.</p><?php endif; ?>
<p class="tq-assess__note">المتاح هو الصفوف الظاهرة فقط. النتيجة إرشادية وليست شهادة اعتماد. للحساب الموجود، يُعرض الاختبار وفق الصف المسجّل في حساب الطالب.</p>
<div class="tq-assess__paid"><h2>جاهز للاشتراك مباشرة؟</h2><p>الباقات المدفوعة مسار منفصل؛ يمكنك مراجعة المحتوى والسعر والمدة قبل اتخاذ قرار الشراء.</p><a class="btn btn--primary" href="<?php echo base_url('plans'); ?>">استعرض الباقات والأسعار</a></div>
</section></main>
