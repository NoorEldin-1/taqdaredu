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
$route['certificate/(:any)'] = "addons/certificate/generate_certificate/$1";

//course bundles
$route['course_bundles/(:any)']               = "addons/course_bundles/index/$1";
$route['course_bundles']                      = "addons/course_bundles";
$route['course_bundles/search/(:any)']        = "addons/course_bundles/search/$1";
$route['course_bundles/search/(:any)/(:any)'] = "addons/course_bundles/search/$1/$1";
$route['bundle_details/(:any)/(:any)']        = "addons/course_bundles/bundle_details/$1";
$route['bundle_details/(:any)']               = "addons/course_bundles/bundle_details/$1/$1";
$route['course_bundles/buy/(:any)']           = "addons/course_bundles/buy/$1";
$route['home/my_bundles']                     = "addons/course_bundles/my_bundles";
$route['home/bundle_invoice/(:any)']          = "addons/course_bundles/invoice/$1";
//end course bundles

//ebook
$route['ebook/ebook_details/(:any)/(:any)'] = "addons/ebook/ebook_details/$1/$2";
$route['ebook']                             = "addons/ebook/ebooks";
$route['ebook_manager/all_ebooks']          = "addons/ebook_manager/all_ebooks";
$route['ebook_manager/add_ebook']           = "addons/ebook_manager/add_ebook";
$route['ebook_manager/payment_history']     = "addons/ebook_manager/payment_history";
$route['ebook_manager/category']            = "addons/ebook_manager/category";
$route['ebook/buy/(:any)']                  = "addons/ebook/buy/$1";
$route['home/my_ebooks']                    = "addons/ebook/my_ebooks";
//end ebook

//BLog
$route['blog/(:any)/(:num)']   = 'blog/details/$1/$2';
$route['blogs']        = "blog/blogs";
$route['blogs/(:any)'] = "blog/blogs/$1";
//End blog

//Custom page
$route['page/(:any)'] = "page/index/$1";
//End Custom page

//tutor booking ..... tutor_booking/tutors
$route['tutors']                    = "addons/tutor_booking/list_of_tuitions";
$route['tutors/(:any)']             = "addons/tutor_booking/list_of_tuitions/$1";
$route['tutor/filter']              = "addons/tutor_booking/list_of_tuitions_after_filter";
$route['schedules_bookings/(:any)'] = "addons/tutor_booking/tutor_details/$1";
$route['my_bookings']               = "addons/tutor_booking/booked_schedules_student";
//End tutor booking

$route['sitemap.xml'] = 'sitemap';

$route['translate_uri_dashes'] = false;

// ---- taqdar write routes ----
// مسارات الكتابة قبل قواعد العرض دائمًا.
//
// `(:any)` في CI3 تُترجم إلى `[^/]+` فتطابق **مقطعًا واحدًا**: فلا
// `teacher/(:any)` ولا `taqdar/teacher/(:any)` تلتقط مسارًا من مقطعين مثل
// `teacher/upload/save`. وبلا قاعدة صريحة يسقط المسار إلى التوجيه الافتراضي
// فيُنادى `Taqdar::teacher('upload','save')` — أي تُعرَض شاشة الرفع نفسها
// ردًّا على طلب كتابة: لا حفظ، ولا خطأ، ولا رسالة.
//
// وهذه المسارات كلّها POST فقط، ترفض GET من داخل المتحكّم بـ show_404().
$route['teacher/upload/save']       = 'taqdar/upload_save';
$route['teacher/marking/approve']   = 'taqdar/marking_approve';
$route['teacher/sessions/save']     = 'taqdar/sessions_save';
$route['teacher/wallet/withdraw']   = 'taqdar/wallet_withdraw';
$route['teacher/questions/import']  = 'taqdar/questions_import';
$route['parent/messages/compose']   = 'taqdar/parent_message_send';
$route['parent/children/link']      = 'taqdar/parent_child_link';
$route['parent/settings/save']      = 'taqdar/parent_settings_save';

// المرادفات ببادئة taqdar/ — لئلّا يسقط المسار الطويل إلى دالّة العرض
$route['taqdar/teacher/upload/save']      = 'taqdar/upload_save';
$route['taqdar/teacher/marking/approve']  = 'taqdar/marking_approve';
$route['taqdar/teacher/sessions/save']    = 'taqdar/sessions_save';
$route['taqdar/teacher/wallet/withdraw']  = 'taqdar/wallet_withdraw';
$route['taqdar/teacher/questions/import'] = 'taqdar/questions_import';
$route['taqdar/parent/messages/compose']  = 'taqdar/parent_message_send';
$route['taqdar/parent/children/link']     = 'taqdar/parent_child_link';
$route['taqdar/parent/settings/save']     = 'taqdar/parent_settings_save';

// ---- taqdar certificates ----
// شاشة الشهادات تشير إلى هذين المسارين، وكانا 404 لأن الدالّتين لم توجدا.
// `verify` عامّة عمدًا: التحقّق من شهادة يقوم به من لا حساب له.
// و`.htaccess` يحوّل `taqdar/(.*)` إلى `/student/$1` بـ301، فالمسار الواصل
// فعلًا هو `student/...` — والقاعدتان الأوليان للاحتياط لو تغيّر التحويل.
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
// بوّابات بأسماء أدوارها بدل بادئة taqdar/ — مطابقة لمسارات تطبيق Flutter.
$route['student']                = 'taqdar/home';
$route['student/on-demand']      = 'taqdar/on_demand';
$route['student/lesson/(:num)']            = 'taqdar/lesson/$1';
$route['student/lesson/(:num)/(:num)']     = 'taqdar/lesson/$1/$2';
$route['student/settings/save']        = 'taqdar/settings_save';
$route['student/reviews']              = 'taqdar/reviews';
$route['teacher/students/message']     = 'taqdar/students_message';
$route['taqdar/teacher/students/message'] = 'taqdar/students_message';
$route['student/subscribe-path']       = 'taqdar/subscribe_path';
$route['student/parent-link']            = 'taqdar/parent_link_respond';
$route['student/(:any)']         = 'taqdar/$1';
$route['teacher']                = 'taqdar/teacher/dashboard';
$route['teacher/courses/save']           = 'taqdar/courses_save';
$route['taqdar/teacher/courses/save']    = 'taqdar/courses_save';
$route['teacher/(:any)']         = 'taqdar/teacher/$1';
$route['parent']                 = 'taqdar/parent_portal/children';
$route['parent/(:any)']          = 'taqdar/parent_portal/$1';

// صفحات عامّة بلا بادئة home/
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
// المشغّل القديم لا يعرف بوّابة الإتقان ويكتب في watch_histories وحده،
// ففتحه مباشرةً كان يلتفّ على القفل. يُحوَّل إلى المشغّل الجديد.
$route['home/lesson/(:any)/(:num)/(:num)'] = 'taqdar/lesson/$2/$3';
$route['home/lesson/(:any)/(:num)']        = 'taqdar/lesson/$2';
