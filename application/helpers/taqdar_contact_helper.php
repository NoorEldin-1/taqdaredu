<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * حقول درع نموذج «تواصل معنا» — TQ-CONTACT-SPAM.
 *
 * ثلاثة حقول لا يراها زائر: مصيدتان باسمين يملؤهما أكثر البوتات آليا
 * (`website` · `company`)، وختم موقع بوقت طبع الصفحة يقيس به الخادم كم
 * لبث النموذج مفتوحا. والقاعدة كلها في `Taqdar_contact_model`، وهذا يطبع.
 *
 * والإخفاء بالورقة لا بـ`type="hidden"`: حقل مخفي بالنوع يتخطاه البوت
 * الذي يقرأ الوسم، وحقل نصي منزو خارج الشاشة يملؤه «املأ كل الحقول».
 * و`tabindex="-1"` و`autocomplete="off"` لئلا يقع فيه من يتنقل بالمفاتيح
 * أو يملأ متصفحه النموذج عنه.
 */
if (!function_exists('tq_contact_shield')) {
    function tq_contact_shield()
    {
        $CI = &get_instance();
        $CI->load->model('taqdar_contact_model');

        $stamp = $CI->taqdar_contact_model->stamp();

        /* والركن بالقص لا بالإزاحة: `left:-9999px` في اتجاه RTL يمتد إلى
           **يسار** المستند فيوسعه عشرة آلاف بكسل — فتقرأ صفحة «تواصل معنا»
           على جوال بعرض ٣٤٤ وكأنها بعرض ١٠٣١٠، وينكمش كل ما فيها إلى شريط.
           وهو `‎.tq-skip-link‎` نفسه في [shell.css](../../assets/taqdar/site/css/shell.css). */
        echo '<div aria-hidden="true" style="position:absolute;inset-inline-start:0;top:0;width:1px;height:1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap">'
           . '<label>Website<input type="text" name="website" value="" tabindex="-1" autocomplete="off"></label>'
           . '<label>Company<input type="text" name="company" value="" tabindex="-1" autocomplete="off"></label>'
           . '</div>'
           . '<input type="hidden" name="tq_ts" value="' . html_escape($stamp) . '">';
    }
}

/** خيارات موضوع الرسالة — القالب يطبع منها والخادم يفحص بها. */
if (!function_exists('tq_contact_subjects')) {
    function tq_contact_subjects()
    {
        $CI = &get_instance();
        $CI->load->model('taqdar_contact_model');

        return Taqdar_contact_model::subjects();
    }
}
