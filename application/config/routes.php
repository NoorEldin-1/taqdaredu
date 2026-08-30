<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/user_guide/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
$route['default_controller'] = 'home';
$route['404_override']       = 'home/page_not_found';

/* ---- ما حذف من هنا، ولماذا -------------------------------------------
 *
 * كانت في هذا الموضع عشرون قاعدة تحول إلى `addons/…`:
 * `certificate/(:any)` · `course_bundles/*` · `ebook*` · `tutors*` ·
 * `schedules_bookings/(:any)` · `my_bookings`.
 *
 * و`application/controllers/addons/` **مجلد فارغ**، وجدول `addons` بلا
 * صف واحد. فكل واحدة منها كانت تحول رابطا عاما إلى متحكم غير موجود:
 * الزائر الذي يفتح `/tutors` أو `/ebook` لا يرى 404 مفهومة بل خطأ
 * توجيه، والزاحف يقرؤها روابط مكسورة في الموقع.
 *
 * وشهادات تقدر لها مسارها الخاص أدناه (`student/certificate/(:num)` و
 * `verify/(:any)`) ولا علاقة لها بإضافة Academy المحذوفة، فحذف
 * `certificate/(:any)` لا يمس شهادة واحدة صادرة.
 * --------------------------------------------------------------------- */

//BLog
$route['blog/(:any)/(:num)']   = 'blog/details/$1/$2';
$route['blogs']        = "blog/blogs";
$route['blogs/(:any)'] = "blog/blogs/$1";
//End blog

$route['sitemap.xml'] = 'sitemap';

$route['translate_uri_dashes'] = false;

// ---- تأكيد الحساب بالرمز (OTP) ----
// ثلاث نقاط كتابة تنادى بـ`fetch` من شاشة `sign_up/verification_code`.
// وهي تحت `login/` عمدا: هناك يعيش التسجيل والتحقق كله، والشاشة نفسها
// تحت `sign_up/` لأن `Sign_up` هو من يعرضها.
//
// والقواعد صريحة وإن كانت `login/(:any)` غير موجودة: مقطعان، فلو أضيفت
// يوما قاعدة عرض عامة تحت `login/` سقطت هذه الثلاثة إليها بلا خطأ ظاهر.
// ---- تبديل اللغة (TQ-I18N) ----
// كتابة، فقاعدتها قبل قواعد العرض — و`language` مقطع لا يملكه متحكم اليوم،
// ولكن `(:any)` في قاعدة عرض تضاف غدا تبتلعه فيرد المبدل صفحة بلا تبديل.
$route['language/set'] = 'taqdar_lang/set';

$route['login/otp/verify']  = 'login/otp_verify';
$route['login/otp/resend']  = 'login/otp_resend';
$route['login/otp/channel'] = 'login/otp_channel';

// ======================================================================
// واجهة البرمجة — الإصدار الأول (api/v1)
// ======================================================================
//
// **القواعد صريحة كلها، ولا مفر من ذلك.** `Api.php` الموروث موجود، فبلا
// قاعدة يذهب `api/v1/auth/login` إلى `Api::v1('auth','login')` — و
// `REST_Controller` لا يجد `v1_get` فيرد خطأه هو بشكل غلاف آخر. أي أن
// الصمت هنا لا يعطي 404 مفهومة بل ردا بغلاف ثالث يحير عميل التطبيق.
//
// والبادئة `api/` مقصودة كما `payment/`: `csrf_exclude_uris` في
// [config.php](config.php) يستثني `api/.*` وحدها — والتطبيق لا يحمل رمز
// حماية النماذج ولا كعكته.
//
// والترتيب هو ترتيب هذا الملف كله: **الأخص قبل الأعم**. و`api/v1/(:any)`
// في الذيل تلتقط ما لا قاعدة له فترد JSON مفهومة بدل صفحة HTML كاملة
// يرمي عليها Flutter استثناء تحليل.

// ---- الوثائق ----
// `openapi.json` و`collection.json` قبل `api/docs`: هما مساران مستقلان
// لا وسيطان على الصفحة، والأخص أولا كما في هذا الملف كله.
$route['api/docs/openapi.json']   = 'api_docs/openapi';
$route['api/docs/collection.json']= 'api_docs/collection';
$route['api/docs']                = 'api_docs/index';

// ---- الدخول ----
$route['api/v1/auth/login']       = 'api_v1/auth_login';
$route['api/v1/auth/refresh']     = 'api_v1/auth_refresh';
$route['api/v1/auth/logout-all']  = 'api_v1/auth_logout_all';
$route['api/v1/auth/logout']      = 'api_v1/auth_logout';
$route['api/v1/auth/sessions']    = 'api_v1/auth_sessions';
$route['api/v1/auth/me']          = 'api_v1/auth_me';

// ---- الطالب · ملفي ----
// المسارات الأطول أولا: `profile/activity` مقطعان، و`student/profile`
// مقطعان كذلك — فلولا الترتيب لالتقطت الأولى قاعدة الثانية.
$route['api/v1/student/profile/activity'] = 'api_v1/student_activity';
$route['api/v1/student/profile/mastery']  = 'api_v1/student_mastery';
$route['api/v1/student/profile']          = 'api_v1/student_profile';

// ---- الطالب · الإعدادات ----
// الكتابة قبل العرض: `settings/profile` لا يجوز أن تسقط إلى `settings`.
$route['api/v1/student/settings/profile']          = 'api_v1/settings_profile';
$route['api/v1/student/settings/avatar']           = 'api_v1/settings_avatar';
$route['api/v1/student/settings/password']         = 'api_v1/settings_password';
$route['api/v1/student/settings/notifications']    = 'api_v1/settings_notifications';
$route['api/v1/student/settings/preferences']      = 'api_v1/settings_preferences';
$route['api/v1/student/settings/parent-links/(:num)'] = 'api_v1/settings_parent_link/$1';
$route['api/v1/student/settings/parent-links']     = 'api_v1/settings_parent_links';
$route['api/v1/student/settings/export']           = 'api_v1/settings_export';
$route['api/v1/student/settings']                  = 'api_v1/student_settings';
$route['api/v1/student/account']                   = 'api_v1/account_delete';

// ---- الطالب · الاشتراك والفواتير ----
$route['api/v1/student/subscription/cancel'] = 'api_v1/subscription_cancel';
$route['api/v1/student/subscription']        = 'api_v1/student_subscription';
$route['api/v1/student/invoices/(:num)/pay'] = 'api_v1/invoice_pay/$1';
$route['api/v1/student/invoices/(:num)']     = 'api_v1/student_invoice/$1';
$route['api/v1/student/invoices']            = 'api_v1/student_invoices';

// ---- الطالب · الرئيسية ----
// شاشة الفتح — نداء واحد يجمع الخطوة والسلسلة والهدف والكورسات
// والمواعيد والشارات.
$route['api/v1/student/home'] = 'api_v1/student_home';

// ---- الطالب · التعلم ----
// `courses/(:num)` قبل `courses`: الأخص أولا كما في هذا الملف كله.
$route['api/v1/student/courses/(:num)'] = 'api_v1/student_course/$1';
$route['api/v1/student/courses']        = 'api_v1/student_courses';

// ---- الطالب · الدرس والتقدم ----
// **الكتابة قبل العرض** — وهي القاعدة نفسها التي تحمي `teacher/upload/save`
// في هذا الملف: `(:num)` تلتقط مقطعا واحدا، فبلا القواعد الثلاث الأولى
// يسقط `lessons/88/progress` إلى `student_lesson(88)` — تعرض الدرس ردا
// على نبضة تقدم: لا حفظ، ولا خطأ، ولا شيء يقول لماذا لا يتقدم الشريط.
$route['api/v1/student/lessons/(:num)/progress'] = 'api_v1/lesson_progress/$1';
$route['api/v1/student/lessons/(:num)/complete'] = 'api_v1/lesson_complete/$1';
$route['api/v1/student/lessons/(:num)/notes']    = 'api_v1/lesson_notes/$1';
// اختبار الدرس ثلاثة مقاطع، فقاعدته قبل `lessons/(:num)` كذلك.
$route['api/v1/student/lessons/(:num)/quiz/start'] = 'api_v1/quiz_start/$1';
$route['api/v1/student/lessons/(:num)']            = 'api_v1/student_lesson/$1';
$route['api/v1/student/notes/(:num)']              = 'api_v1/note_delete/$1';
$route['api/v1/student/lessons']                   = 'api_v1/student_lessons';
// المقطع الأخير رمز موقع بـbase64url — حروف وأرقام و`-` و`_`، فلا
// `(:num)` ولا `(:any)`: الثانية تقبله لكن الأولى ترفضه صامتة.
$route['api/v1/student/media/(:any)'] = 'api_v1/student_media/$1';

// ---- الطالب · التقييم ----
$route['api/v1/student/quiz/attempts/(:num)/submit'] = 'api_v1/quiz_submit/$1';
$route['api/v1/student/quiz/attempts/(:num)']        = 'api_v1/quiz_attempt/$1';
$route['api/v1/student/exams']                       = 'api_v1/student_exams';

// ---- الطالب · التمرين ----
// `reviews/answer` قبل `reviews`: كتابة قبل عرض.
$route['api/v1/student/reviews/answer'] = 'api_v1/review_answer';
$route['api/v1/student/reviews']        = 'api_v1/student_reviews';
$route['api/v1/student/mistakes']       = 'api_v1/student_mistakes';

// ---- الفهرس وما لا قاعدة له ----
$route['api/v1']         = 'api_v1/index';
$route['api/v1/(:any)']  = 'api_v1/not_found';
// و`(:any)` تعني `[^/]+` أي **مقطعا واحدا**، فمسار مجهول من مقطعين
// (`api/v1/student/x`) كان يفلت منها إلى التوجيه الافتراضي فيرد CI
// صفحة 404 بـHTML كامل — وعميل Dart يقرأ `<!doctype html` فيرمي
// FormatException بدل أن يعرض «المسار غير موجود». والوعد أن كل رد
// على شكلين لا ثالث لهما، فالقاعدة التالية تمسك البقية.
$route['api/v1/(.+)']    = 'api_v1/not_found';

// ---- taqdar write routes ----
// مسارات الكتابة قبل قواعد العرض دائما.
//
// `(:any)` في CI3 تترجم إلى `[^/]+` فتطابق **مقطعا واحدا**: فلا
// `teacher/(:any)` ولا `taqdar/teacher/(:any)` تلتقط مسارا من مقطعين مثل
// `teacher/upload/save`. وبلا قاعدة صريحة يسقط المسار إلى التوجيه الافتراضي
// فينادى `Taqdar::teacher('upload','save')` — أي تعرض شاشة الرفع نفسها
// ردا على طلب كتابة: لا حفظ، ولا خطأ، ولا رسالة.
//
// وهذه المسارات كلها POST فقط، ترفض GET من داخل المتحكم بـ show_404().
$route['teacher/upload/save']       = 'taqdar/upload_save';
// استوديو المحتوى ومولد الاختبارات — كلها مقطعان، فلا تلتقطها
// `teacher/(:any)` وتسقط إلى دالة العرض.
$route['teacher/studio/generate']   = 'taqdar/studio_generate';
$route['teacher/studio/save']       = 'taqdar/studio_save';
$route['teacher/studio/approve']    = 'taqdar/studio_approve';
$route['teacher/studio/transcript'] = 'taqdar/studio_transcript';
$route['teacher/studio/state']      = 'taqdar/studio_state';
$route['teacher/examgen/save']      = 'taqdar/examgen_save';
$route['teacher/examgen/build']     = 'taqdar/examgen_build';
$route['teacher/marking/approve']   = 'taqdar/marking_approve';
$route['teacher/marking/homework']  = 'taqdar/marking_homework';
$route['teacher/sessions/save']     = 'taqdar/sessions_save';
$route['teacher/sessions/decide']   = 'taqdar/sessions_decide';
$route['teacher/sessions/complete'] = 'taqdar/sessions_complete';
$route['teacher/wallet/withdraw']   = 'taqdar/wallet_withdraw';
$route['teacher/wallet/cancel']     = 'taqdar/wallet_cancel';
$route['teacher/questions/import']  = 'taqdar/questions_import';
$route['teacher/settings/save']     = 'taqdar/teacher_settings_save';
$route['parent/messages/compose']   = 'taqdar/parent_message_send';
$route['parent/children/link']      = 'taqdar/parent_child_link';
$route['parent/settings/save']      = 'taqdar/parent_settings_save';
// الدفع نيابة عن الابن — مقطعان، فلا تلتقطها `parent/(:any)`.
$route['parent/pay/start']          = 'taqdar/parent_pay_start';
/* TQ-COURSE-SALE — ولي الأمر يشتري كورسا مفردا لابنه. كتابة، فقاعدتها
   قبل `parent/(:any)` — وهي تطابق مقطعا واحدا، فبلاها يسقط الطلب إلى
   `Taqdar::parent_portal('pay')`: تعرض الشاشة ردا على طلب شراء، بلا
   شراء ولا خطأ. */
$route['parent/pay/course']         = 'taqdar/parent_pay_course';

// حقا تصدير البيانات وحذف الحساب لا يخصان الطالب وحده — الدالتان
// `Taqdar::export_data()` و`delete_account()` تشترطان تسجيل الدخول لا دورا
// بعينه — وكان مسارهما الوحيد تحت `student/`، فيقرأ المعلم في إعداداته
// رابطا إلى بوابة غير بوابته.
$route['teacher/export-data']       = 'taqdar/export_data';
$route['teacher/delete-account']    = 'taqdar/delete_account';
$route['parent/export-data']        = 'taqdar/export_data';
$route['parent/delete-account']     = 'taqdar/delete_account';

// المرادفات ببادئة taqdar/ — لئلا يسقط المسار الطويل إلى دالة العرض
$route['taqdar/teacher/upload/save']      = 'taqdar/upload_save';
$route['taqdar/teacher/marking/approve']  = 'taqdar/marking_approve';
$route['taqdar/teacher/marking/homework'] = 'taqdar/marking_homework';
$route['taqdar/teacher/sessions/save']    = 'taqdar/sessions_save';
$route['taqdar/teacher/sessions/decide']  = 'taqdar/sessions_decide';
$route['taqdar/teacher/sessions/complete'] = 'taqdar/sessions_complete';
$route['taqdar/student/sessions/request'] = 'taqdar/session_request';
$route['taqdar/student/sessions/pay']     = 'taqdar/session_pay';
$route['taqdar/student/sessions/cancel']  = 'taqdar/session_cancel';
$route['taqdar/teacher/wallet/withdraw']  = 'taqdar/wallet_withdraw';
$route['taqdar/teacher/questions/import'] = 'taqdar/questions_import';
$route['taqdar/teacher/settings/save']    = 'taqdar/teacher_settings_save';
$route['taqdar/parent/messages/compose']  = 'taqdar/parent_message_send';
$route['taqdar/parent/children/link']     = 'taqdar/parent_child_link';
$route['taqdar/parent/settings/save']     = 'taqdar/parent_settings_save';

// ---- taqdar certificates ----
// شاشة الشهادات تشير إلى هذين المسارين، وكانا 404 لأن الدالتين لم توجدا.
// `verify` عامة عمدا: التحقق من شهادة يقوم به من لا حساب له.
// و`.htaccess` يحول `taqdar/(.*)` إلى `/student/$1` بـ301، فالمسار الواصل
// فعلا هو `student/...` — والقاعدتان الأوليان للاحتياط لو تغير التحويل.
// رمز QR للشهادة — مقطعان، فقاعدته قبل قاعدة عرض الشهادة.
$route['student/certificate/(:num)/qr'] = 'taqdar/certificate_qr/$1';
$route['taqdar/certificate/(:num)/qr']  = 'taqdar/certificate_qr/$1';
$route['taqdar/certificate/(:num)']  = 'taqdar/certificate/$1';
$route['taqdar/verify/(:any)']       = 'taqdar/verify/$1';
$route['student/certificate/(:num)'] = 'taqdar/certificate/$1';
$route['student/verify/(:any)']      = 'taqdar/verify/$1';
$route['verify/(:any)']              = 'taqdar/verify/$1';
$route['verify']                     = 'taqdar/verify';

// ---- taqdar portal routes ----
$route['taqdar/on-demand']      = 'taqdar/on_demand';
$route['taqdar/teacher']        = 'taqdar/teacher/dashboard';
$route['taqdar/teacher/(:any)'] = 'taqdar/teacher/$1';
$route['taqdar/parent']         = 'taqdar/parent_portal/children';
$route['taqdar/parent/(:any)']  = 'taqdar/parent_portal/$1';

// ---- taqdar clean routes ----
// بوابات بأسماء أدوارها بدل بادئة taqdar/ — مطابقة لمسارات تطبيق Flutter.
$route['student']                = 'taqdar/home';
$route['student/on-demand']      = 'taqdar/on_demand';
$route['student/lesson/(:num)']            = 'taqdar/lesson/$1';
$route['student/lesson/(:num)/(:num)']     = 'taqdar/lesson/$1/$2';
$route['student/settings/save']        = 'taqdar/settings_save';
/* الحصص عند الطالب — كتابة، فقواعدها قبل قواعد العرض.
   وكانت الثلاث غائبة كلها: النموذج يرسل إلى `student/on-demand` نفسه
   والعرض يكتب في القاعدة (TQ-SESSION-PAY). */
$route['student/sessions/request']     = 'taqdar/session_request';
$route['student/sessions/pay']         = 'taqdar/session_pay';
$route['student/sessions/cancel']      = 'taqdar/session_cancel';
$route['student/reviews']              = 'taqdar/reviews';
$route['teacher/students/message']     = 'taqdar/students_message';
$route['taqdar/teacher/students/message'] = 'taqdar/students_message';
$route['student/subscribe-path']       = 'taqdar/subscribe_path';
/* TQ-COURSE-SALE — شراء كورس مفرد. كتابة، فقاعدته قبل قواعد العرض
   وقبل `student/(:any)` — وهي تطابق مقطعا واحدا، فبلا هذه القاعدة يسقط
   الطلب إلى `Taqdar::buy('course')` ولا دالة بهذا الاسم. */
$route['student/buy-course']           = 'taqdar/buy_course';
// ---- التهيئة ووضع الامتحان والتلعيب ----
// الكتابة قبل العرض كما في كل هذا الملف: `student/(:any)` تطابق مقطعا
// واحدا، فبلا `student/setup/save` صريحة يسقط الطلب إلى
// `Taqdar::setup('save')` — تعرض شاشة التهيئة ردا على حفظها، فيظن الطالب
// أن خطته لم تحفظ ويعيدها، وهي لم تحفظ فعلا.
$route['student/setup/save']     = 'taqdar/setup_save';
$route['student/setup']          = 'taqdar/setup';
$route['student/exam-mode']      = 'taqdar/exam_mode_save';
$route['student/gamify']         = 'taqdar/gamify_save';
// `profile` اسم دالة محجوز في متحكم Academy الموروث، فالدالة هنا
// `profile_page` والمسار يبقى نظيفا كما يقرؤه الطالب.
$route['student/profile']        = 'taqdar/profile_page';
// ---- الاختبار التشخيصي ----
// مسارا الكتابة قبل مسار العرض، وكلاهما مقطعان: `student/(:any)` تطابق
// مقطعا واحدا، فبلا هاتين القاعدتين يسقط `student/placement/submit` إلى
// `Taqdar::placement('submit')` — تعرض الشاشة ردا على تسليم اختبار، بلا
// تصحيح ولا نتيجة ولا خطأ. وهذا هو الصمت الذي تحذر منه CLAUDE.md بعينه.
$route['student/placement/start']      = 'taqdar/placement_start';
$route['student/placement/submit']     = 'taqdar/placement_submit';
$route['student/placement']            = 'taqdar/placement';
// دفع فاتورة قائمة بالبطاقة. قاعدة صريحة قبل `student/(:any)`: بدونها يصل
// `pay-invoice` إلى `Taqdar::pay_invoice()` وهي غير موجودة، فيرد 404 على زر
// «ادفع الآن» في صفحة الاشتراك.
$route['student/pay-invoice']          = 'taqdar_pay/start';
$route['student/parent-link']            = 'taqdar/parent_link_respond';
// قلب التفضيل. قاعدة صريحة قبل `student/(:any)`: بدونها يصل الاسم كما هو
// إلى `Taqdar::favourite()` وهي غير موجودة، فيرد 404 على كل ضغطة قلب.
$route['student/favourite']            = 'taqdar/favourite_toggle';

// ---- البحث داخل البوابة ----
// صندوق البحث في ترويسة البوابة يصدر إلى `<الدور>/search`. و`student/search`
// يصل وحده عبر `student/(:any)`، أما `teacher/search` فيسقط إلى
// `Taqdar::teacher('search')` و`parent/search` إلى `Taqdar::parent_portal('search')`
// وليس فيهما قسم بهذا الاسم — فترد 404 على زر في ترويسة كل صفحة.
$route['student/search']         = 'taqdar/search';
$route['teacher/search']         = 'taqdar/search';
$route['parent/search']          = 'taqdar/search';

$route['student/(:any)']         = 'taqdar/$1';
$route['teacher']                = 'taqdar/teacher/dashboard';
$route['teacher/courses/save']           = 'taqdar/courses_save';
$route['taqdar/teacher/courses/save']    = 'taqdar/courses_save';

// ---- المنهج عند المعلم: الأقسام والدروس ----
// كل هذه **قبل** `teacher/(:any)` وقبل بعضها بترتيب الأخص أولا. و`(:any)`
// في CI3 تعني `[^/]+` أي مقطعا واحدا، فبلا هذه القواعد يسقط
// `teacher/lesson/save` إلى `Taqdar::teacher('lesson')` — وليس في خريطة
// أقسام البوابة قسم بهذا الاسم، فيرد 404 على كل حفظ درس. وهو الصمت الذي
// تصفه CLAUDE.md في أول قواعد التوجيه.
$route['teacher/section/save']    = 'taqdar/section_save';
$route['teacher/section/delete']  = 'taqdar/section_delete';
$route['teacher/section/sort']    = 'taqdar/section_sort';
$route['teacher/lesson/save']     = 'taqdar/lesson_save';
$route['teacher/lesson/delete']   = 'taqdar/lesson_delete';
$route['teacher/lesson/sort']     = 'taqdar/lesson_sort';
$route['teacher/lesson/move']     = 'taqdar/lesson_move';
// اختبار الدرس — الكتابة قبل العرض للسبب نفسه.
$route['teacher/quiz/question']   = 'taqdar/quiz_question_save';
$route['teacher/quiz/delete']     = 'taqdar/quiz_question_delete';
$route['teacher/quiz/settings']   = 'taqdar/quiz_settings_save';
// إعدادات الكورس — الحفظ قبل العرض، والعرض قبل `teacher/course/(:num)`
// لأن `teacher/course/12/settings` ثلاثة مقاطع و`(:num)` يلتقط الثاني
// وحده، فبلا هذه القاعدة يسقط إلى `teacher_course(12, 'settings')`.
$route['teacher/course/save']            = 'taqdar/course_save';
$route['taqdar/teacher/course/save']     = 'taqdar/course_save';
$route['teacher/course/new']             = 'taqdar/teacher_course_form/0';
$route['teacher/course/(:num)/settings'] = 'taqdar/teacher_course_form/$1';
// شاشتا العرض — بعد قواعد الكتابة.
$route['teacher/course/(:num)']   = 'taqdar/teacher_course/$1';
$route['teacher/quiz/(:num)']     = 'taqdar/teacher_quiz/$1';

$route['teacher/(:any)']         = 'taqdar/teacher/$1';
$route['parent']                 = 'taqdar/parent_portal/children';
$route['parent/(:any)']          = 'taqdar/parent_portal/$1';

// صفحات عامة بلا بادئة home/
$route['courses']                = 'home/courses';
$route['courses/(:any)']         = 'home/courses/$1';
$route['course/(:any)']          = 'home/course/$1';
// ---- الكتالوج الموحد ----
// `catalog/results` قبل `catalog`: هما مساران مستقلان لا واحد بوسيط،
// وترتيبهما هنا لا يضر — لكن الأخص أولا هو القاعدة المتبعة في هذا
// الملف كله، فلا يقرأ لاحق أن الترتيب اعتباطي فيبدله.
// وجزء النتائج **مسار مستقل** لا معامل على الصفحة: صفحة كاملة ترد على
// طلب يريد شبكة بطاقات تحمل الترويسة والتذييل والأصول كلها في كل حرف
// يكتبه الزائر في صندوق البحث.
$route['catalog/results']      = 'taqdar/catalog_results';
$route['catalog']              = 'taqdar/catalog';
$route['book/(:any)']          = 'taqdar/book_page/$1';
$route['competition/(:any)']   = 'taqdar/competition_page/$1';
// `books` كانت صفحة الكتب وحدها، وصارت الكتب نوعا في الكتالوج.
// و`.htaccess` يحولها بـ301؛ وهذه القاعدة احتياط لو عطل التحويل —
// فيصل الزائر إلى الكتب مرشحة بدل 404.
$route['books']                = 'taqdar/catalog';
$route['instructor/(:num)']    = 'taqdar/instructor_page/$1';
$route['teachers']                = 'taqdar/site_page/site_teachers';
$route['students']                = 'taqdar/site_page/site_students';
$route['parents']                 = 'taqdar/site_page/site_parents';
$route['forgot_password']         = 'home/forgot_password';
$route['course/(:any)/(:num)'] = 'home/course/$1/$2';
$route['path/(:any)']          = 'taqdar/path_page/$1';
$route['plan/(:any)']          = 'taqdar/plan_page/$1';
$route['checkout/(:any)']      = 'taqdar/checkout/$1';
/* TQ-COURSE-SALE — شاشة تأكيد شراء كورس مفرد.
   ورقم لا مسمى: الرمز في `/checkout/<code>` عمود فريد في `plans`، ولا
   نظير له في `course` — و`slugify(title)` يتكرر بين كورسين بالاسم نفسه
   فيفتح الرابط ما لم يقصد. */
$route['course-checkout/(:num)'] = 'taqdar/course_checkout/$1';

// ---- بوابة تاب ----
// البادئة `payment/` مقصودة لا مصادفة: `csrf_exclude_uris` في
// [config.php](config.php) يستثني `payment/.*` وحدها، والويبهوك يأتي من خادم
// تاب بلا كعكة ولا رمز حماية — فبادئة أخرى تعني 403 على كل نداء، وكل دفعة
// يغلق صاحبها المتصفح بعدها تبقى محصلة بلا اشتراك.
// والقاعدتان قبل التوجيه الافتراضي: بدونهما يسقط `payment/tap/return` إلى
// `Payment::tap('return')` وليست في متحكم Academy دالة بهذا الاسم.
$route['payment/tap/return']   = 'taqdar_pay/back';
$route['payment/tap/webhook']  = 'taqdar_pay/webhook';

$route['pay/(:any)']           = 'taqdar/gateway_callback/$1';
$route['competitions']        = 'taqdar/competitions';
$route['competitions/join']   = 'taqdar/competition_join';
$route['about']                  = 'home/about_us';
$route['contact']                = 'home/contact_us';
$route['faq']                    = 'home/faq';
$route['terms']                  = 'home/terms_and_condition';
$route['privacy']                = 'home/privacy_policy';
$route['refund']                 = 'home/refund_policy';
$route['plans']                  = 'taqdar/plans';
$route['categories']             = 'taqdar/categories';
$route['search']                 = 'taqdar/site_search';
$route['instructors']            = 'home/instructor_list';
$route['my-courses']             = 'home/my_courses';
$route['profile']                = 'home/profile';

// ---- legacy player redirect ----
// المشغل القديم لا يعرف بوابة الإتقان ويكتب في watch_histories وحده،
// ففتحه مباشرة كان يلتف على القفل. يحول إلى المشغل الجديد.
$route['home/lesson/(:any)/(:num)/(:num)'] = 'taqdar/lesson/$2/$3';
$route['home/lesson/(:any)/(:num)']        = 'taqdar/lesson/$2';
