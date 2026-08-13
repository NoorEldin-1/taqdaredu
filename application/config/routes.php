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
$route['teacher/marking/approve']   = 'taqdar/marking_approve';
$route['teacher/marking/homework']  = 'taqdar/marking_homework';
$route['teacher/sessions/save']     = 'taqdar/sessions_save';
$route['teacher/sessions/decide']   = 'taqdar/sessions_decide';
$route['teacher/wallet/withdraw']   = 'taqdar/wallet_withdraw';
$route['teacher/wallet/cancel']     = 'taqdar/wallet_cancel';
$route['teacher/questions/import']  = 'taqdar/questions_import';
$route['teacher/settings/save']     = 'taqdar/teacher_settings_save';
$route['parent/messages/compose']   = 'taqdar/parent_message_send';
$route['parent/children/link']      = 'taqdar/parent_child_link';
$route['parent/settings/save']      = 'taqdar/parent_settings_save';

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
$route['student/reviews']              = 'taqdar/reviews';
$route['teacher/students/message']     = 'taqdar/students_message';
$route['taqdar/teacher/students/message'] = 'taqdar/students_message';
$route['student/subscribe-path']       = 'taqdar/subscribe_path';
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
$route['teacher/(:any)']         = 'taqdar/teacher/$1';
$route['parent']                 = 'taqdar/parent_portal/children';
$route['parent/(:any)']          = 'taqdar/parent_portal/$1';

// صفحات عامة بلا بادئة home/
$route['courses']                = 'home/courses';
$route['courses/(:any)']         = 'home/courses/$1';
$route['course/(:any)']          = 'home/course/$1';
$route['books']                   = 'taqdar/site_page/site_books';
$route['instructor/(:num)']    = 'taqdar/instructor_page/$1';
$route['teachers']                = 'taqdar/site_page/site_teachers';
$route['students']                = 'taqdar/site_page/site_students';
$route['parents']                 = 'taqdar/site_page/site_parents';
$route['forgot_password']         = 'home/forgot_password';
$route['course/(:any)/(:num)'] = 'home/course/$1/$2';
$route['path/(:any)']          = 'taqdar/path_page/$1';
$route['plan/(:any)']          = 'taqdar/plan_page/$1';
$route['checkout/(:any)']      = 'taqdar/checkout/$1';

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
