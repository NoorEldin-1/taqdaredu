<?php
/**
 * ثيم تقدر — الغلاف.
 *
 * الاتجاه نتيجة للغة لا إعداد مستقل، ولذلك يشتق dir من لغة النظام
 * ولا يقرأ من تفضيل منفصل. وصفحات البوابات تفتح غلافها بنفسها عبر
 * portal_open.php / portal_close.php، فالغلاف هنا لا يعرف بها.
 */
$tq_lang     = get_settings('language') ?: 'arabic';
$tq_dirs     = json_decode(get_settings('language_dirs') ?: '{}', true);
$tq_session  = $this->session->userdata('language');
$tq_active   = $tq_session ?: $tq_lang;
$tq_dir      = $tq_dirs[$tq_active] ?? 'ltr';
$tq_iso      = getIsoCode(ucfirst($tq_active)) ?: 'ar';

// صفحات البوابة تعرض بلا ترويسة الموقع العام ولا تذييله
$tq_portal_pages = [
    'tq_reviews', 'tq_parent_settings', 'tq_delete_account',
    'tq_home', 'tq_lesson', 'tq_subscription', 'tq_lessons', 'tq_tasks', 'tq_exams', 'tq_on_demand', 'tq_materials',
    'tq_reports', 'tq_favourites', 'tq_messages', 'tq_notifications', 'tq_calendar',
    'tq_settings', 'tq_certificates', 'tq_payments',
    'tq_teacher_dashboard', 'tq_teacher_courses', 'tq_teacher_upload', 'tq_teacher_questions',
    'tq_teacher_marking', 'tq_teacher_students', 'tq_teacher_sessions', 'tq_teacher_wallet',
    'tq_parent_children', 'tq_parent_child', 'tq_parent_reports', 'tq_parent_weekly',
    'tq_parent_payments', 'tq_parent_messages', 'tq_parent_alerts',
];
$tq_is_portal = isset($page_name) && in_array($page_name, $tq_portal_pages, true);

/* الصفحات المنقولة إلى التصميم الجديد. تنمو صفحة صفحة، وحذف اسم منها
   يرجع تلك الصفحة إلى حالها القديم في ثوان — من غير لمس ملف آخر. */
$tq_site_pages = array('home', 'home_elegant', 'courses_page', 'site_books', 'site_teachers', 'site_students', 'site_parents', 'blogs', 'about_us', 'contact_us', 'site_path', 'login', 'sign_up', 'forgot_password', 'terms_and_condition', 'privacy_policy', 'refund_policy', 'website_faq', '404', 'plans', 'categories', 'blog_details', 'instructor_page', 'competitions', 'site_search', 'site_plan', 'site_checkout');
$tq_is_site = !$tq_is_portal && isset($page_name) && in_array($page_name, $tq_site_pages, true);
?>
<!DOCTYPE html>
<html lang="<?php echo html_escape($tq_iso); ?>" dir="<?php echo html_escape($tq_dir); ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <?php /* الاستثناء الوحيد للون المباشر في الثيم كله: خاصية meta لا تقبل
       متغير CSS، فالقيمة هنا نسخة من --tq-navy وتحدث معه. */ ?>
    <?php /* لون شريط المتصفح من الهوية: كان `#132549` كحليا لا وجود له
        في الموقع، فيظهر شريط بلون غريب على الجوال. */ ?>
    <meta name="theme-color" content="#023331">

    <?php include 'seo.php'; ?>
    <?php include 'includes_top.php'; ?>
</head>

<body class="tq-body<?php echo $tq_is_portal ? ' tq-body--portal' : ''; ?>">

    <a class="tq-skip-link" href="#tq-main">تخط إلى المحتوى</a>

    <?php
    $my_wishlist_items = [];
    if ($user_id = $this->session->userdata('user_id')) {
        $wishlist = $this->user_model->get_all_user($user_id)->row('wishlist');
        if ($wishlist != '') {
            $my_wishlist_items = json_decode($wishlist, true);
        }
    }

    if (!isset($home)) {
        $home = $this->db->where('status', 1)->get('home_pages')->row_array();
    }

    if (!$tq_is_portal) {
        include (!empty($tq_is_site) ? 'site/site_header.php' : 'header.php');
    }

    if (get_frontend_settings('cookie_status') == 'active') {
        include 'eu-cookie.php';
    }

    echo '<main id="tq-main">';
    if (!isset($page_name) || $page_name === null) {
        include $path;
    } else {
        $tq_pf = __DIR__ . '/' . $page_name . '.php';
        if (is_file($tq_pf)) { include $tq_pf; } else { show_404(); }
    }
    echo '</main>';

    if (!$tq_is_portal) {
        include (!empty($tq_is_site) ? 'site/site_footer.php' : 'footer.php');
    }

    include 'includes_bottom.php';
    ?>
</body>
</html>
