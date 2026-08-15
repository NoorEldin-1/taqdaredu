<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
 *  @author   : Creativeitem
 *  date    : 14 september, 2017
 *  Ekattor School Management System Pro
 *  http://codecanyon.net/user/Creativeitem
 *  http://support.creativeitem.com
 */

/**
 * النوافذ المحملة بـAJAX.
 *
 * `showAjaxModal()` و`showLargeModal()` تناديان `modal/popup/<اسم القالب>`،
 * فيدرج القالب من `backend/<الدور>/`. وهي الطريق الوحيد الذي تعرض به
 * عشر شاشات: تعديل قسم، وإضافة درس، وقائمة أنواع الدروس، وغيرها.
 *
 * TQ-MODAL-GUARD — ولم تكن تسأل عن جلسة ولا عن صلاحية.
 *
 * الباني يحمل القاعدة والجلسة ولا يفحص شيئا، والدالة تركب المسار من
 * `$this->session->userdata('role')` ومن مقطع العنوان. أثر ذلك:
 *
 *   · من لا جلسة له: الدور `''` فالمسار `backend//x.php` — يرمي خطأ
 *     تحميل قالب يطبع **المسار المطلق على القرص** في بيئة التطوير.
 *   · ومن له جلسة **بأي دور**: يصل إلى قوالب دوره كلها بلا فحص صلاحية،
 *     ومنها قوالب تعرض بيانات لا تخصه (`course_enrol_list` مثلا يعرض
 *     المسجلين في دورة برقمها وحده).
 *
 * فتفحص الجلسة هنا، ويقيد الاسم بالقائمة البيضاء التي تنادى فعلا: قالب
 * لا ينادى من الواجهة لا سبب لفتحه من العنوان مباشرة.
 */
class Modal extends CI_Controller {

	/**
	 * القوالب التي تفتح في نافذة — وهي التي ينادى بها فعلا من الشاشات.
	 *
	 * القائمة بيضاء لا سوداء: كل اسم جديد يضاف هنا صراحة. والقائمة
	 * السوداء تنسى دائما اسما.
	 */
	private $allowed = array(
		'lesson_types',
		'lesson_add',
		'lesson_edit',
		'sort_lesson',
		'section_add',
		'section_edit',
		'sort_section',
		'quiz_add',
		'quiz_edit',
		'quiz_questions',
		'question_add',
		'question_edit',
		'review_add',
		'review_edit',
		'custom_field_add',
		'custom_field_edit',
		'custom_field_section_edit',
		'custom_field_section_sorting',
		/* `resource_files` كان ينادى من [curriculum.php] ولا اسم له هنا
		   ولا قالب له في المجلد — أي أن زر «الملفات» في كل صف درس كان
		   يفتح نافذة ٤٠٤ بيضاء. */
		'resource_files',
		'change_course_author',
		'course_enrol_list',
		/* `course_add_shortcut` كان هنا وحذف. وقالبه يقرأ `$categories`
		   و`$languages`، وهذه الدالة لا تمررهما — تمررهما
		   `Admin::course_form('add_course_shortcut')` وحدها. فالفتح من
		   هنا كان يطبع تحذيري PHP ثم منتقي تصنيف فارغا. ولا موضع في
		   الواجهة ينادي الاسمين: النموذج غير موصول بزر منذ أعيدت كتابة
		   شاشة الكورسات، وبابه الحي هو «إضافة كورس». */
		'shortcut_add_student',
		'shortcut_enrol_student',
		'student_academic_progress',
		'student_academic_quiz_result',
		'mail_on_course_status_changing_modal',
		'edit_email_template',
		'add_newsletter',
		'edit_newsletter',
		'send_newsletter',
		'blog_category_add',
		'blog_category_edit',
		'ajax_get_section',
		'ajax_get_sub_category',
		/* `contact_reply_form` كان هنا وحذف: قالبه حذف مع إعادة كتابة
		   شاشة «رسائل التواصل» — صار الرد يكتب في الشاشة نفسها لا في
		   نافذة. واسم في القائمة بلا قالب يقابله يعني خطأ تحميل لمن
		   يطرق العنوان مباشرة بدل ٤٠٤ مفهومة. */
		'video_player',
		'admin_permission',
	);

	function __construct()
	{
		parent::__construct();

		date_default_timezone_set(get_settings('timezone'));

		$this->load->database();
		$this->load->library('session');

		/* لا جلسة إدارة، لا نافذة. والرد نص عاد لا صفحة: المستدعي يحقن ما
		   يرد في جسم النافذة، فصفحة دخول كاملة داخل نافذة تقرأ عطلا.

		   والإدارة وحدها: هذه القوالب كلها في `backend/admin/`، ولا
		   مجلد `backend/user/` في هذا المستودع — لوحة المحاضر الموروثة
		   صارت تحويلا بـ301 إلى بوابة المعلم. */
		if ( ! $this->session->userdata('admin_login')) {
			$this->output
			     ->set_status_header(403)
			     ->set_content_type('text/html', 'utf-8')
			     ->set_output('<p class="tqa-flash tqa-flash--err" role="alert">'
			                . 'انتهت جلستك. أعد تسجيل الدخول ثم افتح هذه النافذة من جديد.</p>');
			$this->output->_display();
			exit;
		}
	}

	function popup($page_name = '' , $param2 = '' , $param3 = '', $param4 = '', $param5 = '', $param6 = '', $param7 = '')
	{
		/* الاسم من قائمة معلنة. وبلا ذلك يصير مقطع العنوان مسار ملف —
		   ولو منع `..` فالنتيجة فتح أي قالب في المجلد بلا الصلاحية التي
		   تحرسه في شاشته. */
		if ( ! in_array($page_name, $this->allowed, TRUE)) {
			show_404();
		}

		$logged_in_user_role = 'admin';

		$page_data['param2']	=	$param2;
		$page_data['param3']	=	$param3;
		$page_data['param4']	=	$param4;
		$page_data['param5']	=	$param5;
		$page_data['param6']	=	$param6;
		$page_data['param7']	=	$param7;
		$this->load->view( 'backend/'.$logged_in_user_role.'/'.$page_name.'.php' ,$page_data);
	}
}
