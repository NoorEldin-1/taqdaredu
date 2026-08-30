<?php
/**
 * ترويسة اللوحة.
 *
 * أعيدت كتابتها لتطابق `portal_topbar.php` في البوابات الثلاث: زر الدرج
 * على الجوال · عنوان الصفحة · الرسائل والتواصل · الحساب والخروج.
 *
 * وحذف منها ما لا يعمل: زر «AI Writer» كان مشروطا بـ`addon_status('course_ai')`
 * وهي كاذبة أبدا (لا إضافات مثبتة)، ومنتقي اللغة كان يستعلم عن كل اللغات
 * في كل صفحة ليعرض قائمة من لغة واحدة. واللغة تدار من شاشتها.
 *
 * الخروج **يسأل قبل أن ينفذ**: زر بجوار الحساب يخرج بضغطة خطأ فيضيع
 * نموذج نصف ممتلئ — وهو ما صحح في بوابات الطالب والمعلم وولي الأمر.
 */
$tqa_uid   = (int) $this->session->userdata('user_id');
$tqa_me    = $tqa_uid ? $this->user_model->get_all_user($tqa_uid)->row_array() : null;
$tqa_name  = $tqa_me ? trim($tqa_me['first_name'] . ' ' . $tqa_me['last_name']) : 'مسؤول';
$tqa_photo = $this->user_model->get_user_image_url($tqa_uid);
$tqa_c     = tqa_nav_counts();

/* ثلاث فترات: التحية التي لا تطابق الساعة تقرأ كنص مولد لا كخطاب. */
$tqa_hour = (int) date('G');
if     ($tqa_hour < 12) $tqa_greet = 'صباح الخير';
elseif ($tqa_hour < 18) $tqa_greet = 'طاب يومك';
else                    $tqa_greet = 'مساء الخير';

$tqa_first = explode(' ', $tqa_name)[0];
?>
<header class="tqa-topbar">

    <button class="tqa-iconbtn tqa-rail-toggle" type="button" data-tqa-toggle
            aria-label="فتح القائمة" aria-expanded="false" aria-controls="tqa-rail">
        <?php echo tq_icon('menu'); ?>
    </button>

    <div class="tqa-topbar__title">
        <strong><?php echo html_escape($tqa_greet . '، ' . $tqa_first); ?> 👋</strong>
        <span><?php echo html_escape(isset($page_title) ? $page_title : 'لوحة الإدارة'); ?></span>
    </div>

    <div class="tqa-topbar__actions">

        <?php /* TQ-I18N — مبدل اللغة. ولم يكن في اللوحة باب واحد يغير لغتها:
                 شاشة `manage_profile` تحرر الاسم والصورة وكلمة المرور ولا
                 تعرف اللغة، و«إعدادات المنصة» تحمل لغة **المنصة** لا لغة من
                 يقرأ — فمن يدير المنصة يقلبها على كل مستخدميها ليقرأها هو. */ ?>
        <?php $skin = 'admin'; include APPPATH . 'views/components/tq_lang_switch.php'; ?>

        <a class="tqa-iconbtn" href="<?php echo site_url('admin/message'); ?>"
           aria-label="الرسائل" title="الرسائل">
            <?php echo tq_icon('chat'); ?>
            <?php if (!empty($tqa_c['messages'])): ?>
                <span class="tqa-iconbtn__dot"><?php echo (int) $tqa_c['messages']; ?></span>
                <span class="tqa-sr">رسائل غير مقروءة</span>
            <?php endif; ?>
        </a>

        <a class="tqa-iconbtn" href="<?php echo site_url('admin/contact'); ?>"
           aria-label="رسائل التواصل" title="رسائل التواصل">
            <?php echo tq_icon('mail'); ?>
            <?php if (!empty($tqa_c['contact'])): ?>
                <span class="tqa-iconbtn__dot"><?php echo (int) $tqa_c['contact']; ?></span>
                <span class="tqa-sr">رسائل تواصل لم تقرأ</span>
            <?php endif; ?>
        </a>

        <a class="tqa-topbar__me" href="<?php echo site_url('admin/manage_profile'); ?>"
           title="<?php echo html_escape($tqa_name); ?>">
            <img src="<?php echo $tqa_photo; ?>" alt="">
            <span class="tqa-topbar__me-text">
                <b><?php echo html_escape($tqa_name); ?></b>
                <span>مسؤول</span>
            </span>
        </a>

        <?php /* الخروج نموذج POST لا رابط: رابط يخرج بمجرد فتحه يستدعيه
                 استباق تحميل المتصفح أو زاحف، فيخرج المسؤول بلا أن ينقر. */ ?>
        <form action="<?php echo site_url('login/logout'); ?>" method="post" style="margin:0"
              data-tqa-nolock
              data-tqa-confirm-title="تسجيل الخروج"
              data-tqa-confirm="ستخرج من لوحة الإدارة، وما لم يحفظ في الشاشة المفتوحة يضيع."
              data-tqa-confirm-ok="اخرج">
            <?php echo tq_csrf(); ?>
            <button class="tqa-iconbtn" type="submit" aria-label="تسجيل الخروج" title="تسجيل الخروج">
                <?php echo tq_icon('logout'); ?>
            </button>
        </form>
    </div>
</header>
