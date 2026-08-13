<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * حقول «تطبيق الجوال» — تحمل قيمها كما هي ولا تعرض.
 *
 * ثلاثة أعمدة يكتبها `Crud_model` من هذه الأسماء
 * (`lesson_provider_for_mobile_application` · `html5_video_url_for_mobile_application`
 * · `html5_duration_for_mobile_application`)، وكانت معروضة في نموذجي
 * يوتيوب وفيميو ومخفية بـ`d-none` في البقية — أي أن الحقل نفسه يظهر في
 * شاشة ويختفي في أخرى بلا سبب.
 *
 * ولا أحد يملؤها: تطبيق تقدر يقرأ `video_url` نفسه. لكن **حذف الحقل من
 * النموذج يعني كتابة فراغ فوق القيمة المحفوظة** عند كل حفظ — فتحمل هنا
 * كما هي. وتحذف هذه الحقول يوم يحذف الكتابة إليها من النموذج، لا قبله.
 */
$tq_l = isset($lesson_details) ? $lesson_details : array();
?>
<input type="hidden" name="lesson_provider_for_mobile_application"
       value="<?php echo html_escape($tq_l['video_type_for_mobile_application'] ?? 'html5'); ?>">
<input type="hidden" name="html5_video_url_for_mobile_application"
       value="<?php echo html_escape($tq_l['video_url_for_mobile_application'] ?? ''); ?>">
<input type="hidden" name="html5_duration_for_mobile_application"
       value="<?php echo html_escape($tq_l['duration_for_mobile_application'] ?? '00:00:00'); ?>">
