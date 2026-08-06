<?php
/**
 * فتح غلاف البوابة: القائمة الجانبية + الترويسة.
 * كل صفحة بوابة تبدأ بهذا وتنتهي بـ portal_close.php.
 *
 * المتغيرات المتوقعة من الصفحة (كلها اختيارية):
 *   $tq_nav      مفتاح عنصر القائمة النشط
 *   $tq_role     student | teacher | parent   (افتراضه student)
 *   $tq_title    عنوان الصفحة
 *   $tq_sub      سطر تحت العنوان
 *   $tq_icon     اسم أيقونة (svg sprite)
 *   $tq_tools    HTML لأدوات يمين العنوان
 */
$tq_nav  = $tq_nav  ?? '';
$tq_role = $tq_role ?? 'student';
?>
<?php
/* مكتبة الأشكال — مرة واحدة لكل صفحة بوابة.
   المساعدات المشتركة (`tqs_curriculum` · `tqs_bank_block` · `tqs_stat_strip`)
   تشير إلى رموزها بـ`<use href="#i-…">`، وهي معرفة في سبرايت الموقع الذي
   كانت ترويسة الموقع وحدها تطبعه. فكان كل رمز في صفحات البوابة يرسم فراغا
   بلا خطأ في الكونسول: مربع فارغ مكان القفل والبنك والعلم. */
?>
<?php include __DIR__ . '/site/site_sprite.php'; ?>
<div class="tq-shell">

    <?php include 'portal_rail.php'; ?>

    <div class="tq-shell__main">
        <?php include 'portal_topbar.php'; ?>

        <div class="tq-page">
            <?php
            /* TQ-PORTAL-FLASH — رسائل البوابة كانت تكتب ولا تقرأ.
               `Taqdar.php` يودع ثماني عشرة رسالة (عشر أخطاء وثماني
               نجاحات) بعد كل حفظ وربط وإرسال، ولم يكن في غلاف البوابة
               سطر واحد يعرضها: تحفظ إعداداتك فلا يقال «حفظ»، ويفشل
               الربط فلا يقال لماذا — الشاشة تعود كما هي في الحالين.
               والدخول من بوابة غير بوابتك يقال هنا كذلك. */
            foreach (array(
                'error_message' => 'err',
                'flash_message' => 'ok',
                'info_message'  => 'ok',
            ) as $tq_fk => $tq_fc):
                $tq_fm = $this->session->flashdata($tq_fk);
                if (empty($tq_fm)) continue; ?>
                <p class="tq-flash tq-flash--<?php echo $tq_fc; ?>"
                   role="<?php echo $tq_fc === 'err' ? 'alert' : 'status'; ?>">
                    <?php echo html_escape($tq_fm); ?>
                </p>
            <?php endforeach; ?>

            <?php if (!empty($tq_title)): ?>
                <div class="tq-pagehead">
                    <div>
                        <div class="tq-pagehead__title">
                            <?php if (!empty($tq_icon)): ?>
                                <span class="tq-icon-box tq-pastel--sky" aria-hidden="true">
                                    <?php echo tq_icon($tq_icon); ?>
                                </span>
                            <?php endif; ?>
                            <h1><?php echo html_escape($tq_title); ?></h1>
                        </div>
                        <?php if (!empty($tq_sub)): ?>
                            <p class="tq-pagehead__sub"><?php echo html_escape($tq_sub); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($tq_tools)): ?>
                        <div class="tq-pagehead__tools"><?php echo $tq_tools; ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
