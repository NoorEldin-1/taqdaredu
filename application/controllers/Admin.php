<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Admin extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        date_default_timezone_set(get_settings('timezone'));

        $this->load->database();
        $this->load->library('session');
        /*cache control*/
        $this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        $this->output->set_header('Pragma: no-cache');

        $this->user_model->check_session_data('admin');

        ini_set('memory_limit', '128M');
    }

    public function index()
    {
        if ($this->session->userdata('admin_login') == true) {
            $this->dashboard();
        } else {
            redirect(site_url('login'), 'refresh');
        }
    }

    /* =====================================================================
       TQ-DT — قراءة طلب DataTables والرد عليه.

       ست شاشات في هذا الملف تقرأ الطلب نفسه بالسطور نفسها، وفيها ثلاثة
       أعطال متكررة تظهر كلها للمستخدم بالنافذة البيضاء ذاتها
       («DataTables warning … Ajax error») بلا أن تقول أين:

       ١ — `$this->input->post('order')[0]['column']` تفترض وجود الترتيب.
           و`ajax.reload()` ترسل أحيانا بلا `order`، وأي نداء يدوي كذلك،
           فيرمى على فهرس غير موجود.

       ٢ — `$columns[$idx]` بلا فحص: فهرس عمود من خارج المدى (وهو ما يرسله
           جدول عدلت أعمدته) يرمي أيضا.

       ٣ — `echo json_encode(...)` بلا ترويسة ولا تنظيف مخرج: أي تحذير PHP
           سبق — والبيئة المحلية تطبع التحذيرات — يلتصق بأول قوس فيرفض
           المحلل الرد كله.

       فجمع القراءة والرد هنا: الشاشات تصف أعمدتها ولا تكرر المنطق.
       ===================================================================== */

    /** يقرأ معاملات الطلب مطهرة، ويرد `[limit, start, column, dir, search]`. */
    private function tq_dt_request($columns, $method = 'post')
    {
        $get = function ($k) use ($method) {
            return $method === 'get' ? $this->input->get($k) : $this->input->post($k);
        };

        $limit = (int) $get('length');
        if ($limit <= 0) {
            $limit = 25;                 // `-1` تعني «الكل»، ولا يحد استعلام بلا حد
        }

        $order  = $get('order');
        $column = $columns[0];
        $dir    = 'desc';
        if (is_array($order) && isset($order[0]['column'])) {
            $idx = (int) $order[0]['column'];
            if (isset($columns[$idx])) {
                $column = $columns[$idx];
            }
            $dir = (isset($order[0]['dir']) && strtolower((string) $order[0]['dir']) === 'asc') ? 'asc' : 'desc';
        }

        $search = $get('search');
        $search = is_array($search) ? (string) ($search['value'] ?? '') : '';

        return array($limit, max(0, (int) $get('start')), $column, $dir, $search);
    }

    /** يرد JSON صالحا: ترويسة صريحة، ومخرج نظيف قبله. */
    private function tq_dt_respond($data, $total, $filtered)
    {
        if (ob_get_length()) {
            @ob_clean();
        }
        $this->output
             ->set_content_type('application/json', 'utf-8')
             ->set_output(json_encode(array(
                 'draw'            => (int) $this->input->post('draw') ?: (int) $this->input->get('draw'),
                 'recordsTotal'    => (int) $total,
                 'recordsFiltered' => (int) $filtered,
                 'data'            => $data,
             ), JSON_UNESCAPED_UNICODE));
    }

    /**
     * حولت إلى لوحة القيادة الجديدة.
     *
     * الشاشة القديمة كانت تقرأ جدول `payment` الموروث وتعد كورسات
     * Academy، وترسم إيراد السنة من مبيعات لا تجري بهذه الطريقة: تقدر
     * تبيع باقات واشتراكات، وحقيقتها في `subscriptions` و`invoices`.
     *
     * والتحويل لا الحذف: `admin/dashboard` مكتوب في مئات الإشارات
     * المرجعية وفي علامات متصفحات من يعمل على اللوحة منذ سنة. ورابط
     * يرد 404 لمن حفظه أسوأ من رابط يوصله إلى الشاشة التي حلت محله.
     */
    public function dashboard()
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }
        redirect(site_url('taqdar_admin/overview'), 'location', 302);
    }

    public function categories($param1 = "", $param2 = "")
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }

        // CHECK ACCESS PERMISSION
        check_permission('category');

        if ($param1 == 'add') {

            $response = $this->crud_model->add_category();
            if ($response) {
                $this->session->set_flashdata('flash_message', get_phrase('data_added_successfully'));
            } else {
                $this->session->set_flashdata('error_message', get_phrase('category_name_already_exists'));
            }
            redirect(site_url('admin/categories'), 'refresh');
        } elseif ($param1 == "edit") {

            $response = $this->crud_model->edit_category($param2);
            if ($response) {
                $this->session->set_flashdata('flash_message', get_phrase('data_added_successfully'));
            } else {
                $this->session->set_flashdata('error_message', get_phrase('category_name_already_exists'));
            }
            redirect(site_url('admin/categories'), 'refresh');
        } elseif ($param1 == "delete") {
            $this->crud_model->delete_category($param2);
            $this->session->set_flashdata('flash_message', get_phrase('data_deleted'));
            redirect(site_url('admin/categories'), 'refresh');
        } elseif ($param1 == "sub_category_image") {
            $this->crud_model->delete_subcategory_image($param2);
            $this->session->set_flashdata('flash_message', get_phrase('data_deleted'));
            redirect(site_url('admin/categories'), 'refresh');
        }

        $page_data['page_name']  = 'categories';
        $page_data['page_title'] = get_phrase('categories');
        $page_data['categories'] = $this->crud_model->get_categories($param2);
        $this->load->view('backend/index', $page_data);
    }

    public function category_form($param1 = "", $param2 = "")
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }

        // CHECK ACCESS PERMISSION
        check_permission('category');

        if ($param1 == "add_category") {

            $page_data['page_name']  = 'category_add';
            $page_data['categories'] = $this->crud_model->get_categories()->result_array();
            $page_data['page_title'] = get_phrase('add_category');
        }
        if ($param1 == "edit_category") {

            $page_data['page_name']   = 'category_edit';
            $page_data['page_title']  = get_phrase('edit_category');
            $page_data['categories']  = $this->crud_model->get_categories()->result_array();
            $page_data['category_id'] = $param2;
        }

        $this->load->view('backend/index', $page_data);
    }

    /* TQ-SUBCAT-DEAD — حذفت `sub_categories_by_category_id()` و
       `sub_category_form()` ومعهما ثلاثة قوالب.
     *
     * كانت شاشة أقسام فرعية مستقلة موروثة من **الجيل الأول** من
     * Academy — قوالبها بـBootstrap 3 (`panel panel-primary` ·
     * `col-md-offset-3` · أيقونات `entypo-`)، أي أنها لم تكن تشبه شيئا
     * في هذه اللوحة أصلا لو عرضت.
     *
     * ولم تكن تعرض: كل أزرارها ترد ٤٠٤. النموذج يرسل إلى
     * `admin/sub_categories/0/add`، والحذف إلى `admin/sub_categories/…`،
     * و`sub_categories_by_category_id()` تحول إلى `admin/sub_categories/$id`
     * — و**`Admin::sub_categories()` غير موجودة**. فالإضافة والتعديل
     * والحذف والترشيح، أربعتها، تصل إلى صفحة غير موجودة.
     *
     * ولا رابط إليها من أي شاشة حية: الطريق الفعلي هو `admin/categories`
     * وهي تعرض القسم وأقسامه الفرعية معا وتحرر الاثنين بـ
     * `admin/category_form` (والقسم الفرعي صف في `category` بـ`parent`
     * غير صفر — لا جدول آخر). فشاشة ثانية لعمل واحد تعني موضعين يحرر
     * فيهما، وأحدهما لا يعمل.
     */

    public function instructors($param1 = "", $param2 = "")
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }

        // CHECK ACCESS PERMISSION
        check_permission('user');
        check_permission('instructor');

        if ($param1 == "add") {
            $this->user_model->add_user(true); // PROVIDING TRUE FOR INSTRUCTOR
            redirect(site_url('admin/instructors'), 'refresh');
        } elseif ($param1 == "edit") {
            $this->user_model->edit_user($param2);
            redirect(site_url('admin/instructors'), 'refresh');
        } elseif ($param1 == "delete") {
            $this->user_model->delete_user($param2);
            redirect(site_url('admin/instructors'), 'refresh');
        }

        $page_data = array_merge($page_data ?? [], $this->tq_people_page(2, 1));

        $page_data['page_name']  = 'instructors';
        $page_data['page_title'] = get_phrase('instructor');
        $this->load->view('backend/index', $page_data);
    }

    public function instructor_form($param1 = "", $param2 = "")
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }

        // CHECK ACCESS PERMISSION
        check_permission('user');
        check_permission('instructor');

        if ($param1 == 'add_instructor_form') {
            $page_data['page_name']  = 'instructor_add';
            $page_data['page_title'] = get_phrase('instructor_add');
            $this->load->view('backend/index', $page_data);
        } elseif ($param1 == 'edit_instructor_form') {
            $page_data['page_name']  = 'instructor_edit';
            $page_data['user_id']    = $param2;
            $page_data['page_title'] = get_phrase('instructor_edit');
            $this->load->view('backend/index', $page_data);
        }
    }

    public function users($param1 = "", $param2 = "")
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }

        // CHECK ACCESS PERMISSION
        check_permission('user');
        check_permission('student');

        if ($param1 == "add") {
            $this->user_model->add_user();
            redirect(site_url('admin/users'), 'refresh');
        } elseif ($param1 == "edit") {
            $this->user_model->edit_user($param2);
            redirect(site_url('admin/users'), 'refresh');
        } elseif ($param1 == "delete") {
            $this->user_model->delete_user($param2);
            redirect(site_url('admin/users'), 'refresh');
        }

        $page_data = array_merge($page_data ?? [], $this->tq_people_page(2, 0));

        $page_data['page_name']  = 'users';
        $page_data['page_title'] = get_phrase('student');
        $this->load->view('backend/index', $page_data);
    }

    /**
     * صفحة حسابات — مشتركة بين شاشتي الطلاب والمعلمين.
     *
     * TQ-DT-GONE — كانت كل واحدة جدول DataTables يجلب صفوفه بـPOST من
     * `server_side_users_data` / `server_side_instructors_data`، والمتحكم
     * يبني الصور والأزرار HTML بالسلاسل. ومعها عطلان يشتركان فيهما:
     *
     * ١ — **العد الكلي يخالف الترشيح.** `total_number_of_row` يعد
     *     `role_id != 1` — أي الطلاب والمعلمين معا — بينما الصفوف تجلب
     *     بشرط آخر. فشاشة الطلاب تقول «١ من ٥٠» وفيها تسعة.
     * ٢ — **عدد الكورسات المسجلة استعلام لكل صف.**
     */
    private function tq_people_page($role_id, $is_instructor)
    {
        $per  = 25;
        $page = max(1, (int) $this->input->get('page'));
        $q    = trim((string) $this->input->get('q', true));

        $scope = function () use ($role_id, $is_instructor, $q) {
            $this->db->where('role_id', $role_id);
            $this->db->where('is_instructor', $is_instructor);
            if ($q !== '') {
                $this->db->group_start()
                         ->like('first_name', $q)
                         ->or_like('last_name', $q)
                         ->or_like('email', $q)
                         ->or_like('phone', $q)
                         ->group_end();
            }
        };

        $scope();
        $total = (int) $this->db->count_all_results('users');

        $pages = max(1, (int) ceil($total / $per));
        $page  = min($page, $pages);

        $scope();
        $rows = $this->db->select('id, first_name, last_name, email, phone, status')
                         ->order_by('id', 'DESC')
                         ->limit($per, ($page - 1) * $per)
                         ->get('users')->result_array();

        /* عدد التسجيلات لكل حساب — استعلام واحد لكل الصفحة. */
        $enrols = [];
        $ids    = array_column($rows, 'id');
        if ($ids) {
            foreach ($this->db->select('user_id AS k, COUNT(*) AS n')
                              ->where_in('user_id', $ids)
                              ->group_by('user_id')
                              ->get('enrol')->result_array() as $r) {
                $enrols[(int) $r['k']] = (int) $r['n'];
            }
        }

        return [
            'rows'       => $rows,
            'enrols'     => $enrols,
            'total'      => $total,
            'page_no'    => $page,
            'page_count' => $pages,
            'per_page'   => $per,
            'search'     => $q,
        ];
    }

    public function server_side_users_data()
    {

        $data = [];
        //mentioned all with colum of database table that related with html table
        $columns = ['id', 'id', 'first_name', 'email', 'phone', 'id', 'id'];

        // انظر tq_dt_request: الترتيب والطول والبحث تقرأ مطهرة ومحصنة من الغياب
        list($limit, $start, $column_index, $dir, $tq_dt_search) = $this->tq_dt_request($columns);
        $total_number_of_row = $this->db->where('role_id !=', 1)->get('users')->num_rows();

        $filtered_number_of_row = $total_number_of_row;
        $search                 = $tq_dt_search;

        if (empty($search)) {
            $this->db->select('*');
            $this->db->limit($limit, $start);
            $this->db->order_by($column_index, $dir);
            $this->db->where('role_id', 2);
            $students = $this->db->get('users')->result_array();
        } else {
            $this->db->select('*');
            $this->db->like('first_name', $search);
            $this->db->or_like('last_name', $search);
            $this->db->or_like('email', $search);
            $this->db->or_like('phone', $search);
            $this->db->where('role_id', 2);
            $this->db->limit($limit, $start);
            $this->db->order_by($column_index, $dir);
            $students = $this->db->get('users')->result_array();

            $this->db->select('*');
            $this->db->like('first_name', $search);
            $this->db->or_like('last_name', $search);
            $this->db->or_like('email', $search);
            $this->db->or_like('phone', $search);
            $this->db->where('role_id', 2);
            $filtered_number_of_row = $this->db->get('users')->num_rows();
        }

        foreach ($students as $key => $student):

            //photo
            $photo = '<img src="' . $this->user_model->get_user_image_url($student['id']) . '" alt="" height="50" width="50" class="img-fluid rounded-circle img-thumbnail">';

            //user name
            if ($student['status'] != 1) {
                $status = '<small><p>' . get_phrase('status') . '<span class="badge badge-danger-lighten">' . get_phrase('unverified') . '</span></p></small>';
            } else {
                $status = '';
            }
            $name = $student['first_name'] . ' ' . $student['last_name'] . $status;

            //user email
            $email = $student['email'];

            //enrolled courses
            $enrolled_courses       = $this->crud_model->enrol_history_by_user_id($student['id']);
            $enrolled_courses_title = '<ul>';
            foreach ($enrolled_courses->result_array() as $enrolled_course):
                $course_details = $this->crud_model->get_course_by_id($enrolled_course['course_id'])->row_array();
                $enrolled_courses_title .= '<li>' . $course_details['title'] . '</li>';
            endforeach;
            $enrolled_courses_title .= '</ul>';

            $action = '<div class="dropright dropright">
		                            <button type="button" class="btn btn-sm btn-outline-primary btn-rounded btn-icon" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
		                                <i class="mdi mdi-dots-vertical"></i>
		                            </button>
		                            <ul class="dropdown-menu">
		                                <li><a class="dropdown-item" href="' . site_url('admin/user_form/edit_user_form/' . $student['id']) . '">' . get_phrase('edit') . '</a></li>
		                                <li><a class="dropdown-item" href="#" onclick="confirm_modal(&#39;' . site_url('admin/users/delete/' . $student['id']) . '&#39;);">' . get_phrase('delete') . '</a></li>
		                            </ul>
		                        </div>';

            $nestedData['key']              = ++$key;
            $nestedData['photo']            = $photo;
            $nestedData['name']             = $name;
            $nestedData['email']            = $email;
            $nestedData['phone']            = $student['phone'];
            $nestedData['enrolled_courses'] = $enrolled_courses_title;
            $nestedData['action']           = $action . '<script>$("a, i").tooltip();</script>';
            $data[]                         = $nestedData;
        endforeach;

        $this->tq_dt_respond($data, $total_number_of_row, $filtered_number_of_row);
    }

    public function server_side_instructors_data()
    {

        $data = [];
        //mentioned all with colum of database table that related with html table
        $columns = ['id', 'id', 'first_name', 'email', 'phone', 'id', 'id'];

        // انظر tq_dt_request: الترتيب والطول والبحث تقرأ مطهرة ومحصنة من الغياب
        list($limit, $start, $column_index, $dir, $tq_dt_search) = $this->tq_dt_request($columns);
        $total_number_of_row = $this->db->where('is_instructor', 1)->where('role_id !=', 1)->get('users')->num_rows();

        $filtered_number_of_row = $total_number_of_row;
        $search                 = $tq_dt_search;

        if (empty($search)) {
            $this->db->select('*');
            $this->db->limit($limit, $start);
            $this->db->order_by($column_index, $dir);
            $this->db->group_start();
            $this->db->where('role_id', 2);
            $this->db->where('is_instructor', 1);
            $this->db->group_end();
            $instructors = $this->db->get('users')->result_array();
        } else {
            $this->db->select('*');
            $this->db->group_start();
            $this->db->like('first_name', $search);
            $this->db->or_like('last_name', $search);
            $this->db->or_like('email', $search);
            $this->db->or_like('phone', $search);
            $this->db->group_end();
            $this->db->group_start();
            $this->db->where('role_id', 2);
            $this->db->where('is_instructor', 1);
            $this->db->group_end();
            $this->db->limit($limit, $start);
            $this->db->order_by($column_index, $dir);
            $instructors = $this->db->get('users')->result_array();

            $this->db->select('*');
            $this->db->group_start();
            $this->db->like('first_name', $search);
            $this->db->or_like('last_name', $search);
            $this->db->or_like('email', $search);
            $this->db->or_like('phone', $search);
            $this->db->group_end();
            $this->db->group_start();
            $this->db->where('role_id', 2);
            $this->db->where('is_instructor', 1);
            $this->db->group_end();
            $filtered_number_of_row = $this->db->get('users')->num_rows();
        }

        foreach ($instructors as $key => $instructor):

            //photo
            $photo = '<img src="' . $this->user_model->get_user_image_url($instructor['id']) . '" alt="" height="50" width="50" class="img-fluid rounded-circle img-thumbnail">';

            //user name
            if ($instructor['status'] != 1) {
                $status = '<small><p>' . get_phrase('status') . '<span class="badge badge-danger-lighten">' . get_phrase('unverified') . '</span></p></small>';
            } else {
                $status = '';
            }
            $name = $instructor['first_name'] . ' ' . $instructor['last_name'] . $status;

            //user email
            $email = $instructor['email'];

            //enrolled courses
            $enrolled_courses       = $this->crud_model->enrol_history_by_user_id($instructor['id']);
            $enrolled_courses_title = '<ul>';
            foreach ($enrolled_courses->result_array() as $enrolled_course):
                $course_details = $this->crud_model->get_course_by_id($enrolled_course['course_id'])->row_array();
                $enrolled_courses_title .= '<li>' . $course_details['title'] . '</li>';
            endforeach;
            $enrolled_courses_title .= '</ul>';

            $action = '<div class="dropright dropright">
		                            <button type="button" class="btn btn-sm btn-outline-primary btn-rounded btn-icon" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
		                                <i class="mdi mdi-dots-vertical"></i>
		                            </button>
		                            <ul class="dropdown-menu">
		                                <li><a class="dropdown-item" href="' . site_url('admin/courses?category_id=all&status=all&instructor_id=' . $instructor['id'] . '&price=all') . '">' . get_phrase('view_courses') . '</a></li>
		                                <li><a class="dropdown-item" href="' . site_url('admin/instructor_form/edit_instructor_form/' . $instructor['id']) . '">' . get_phrase('edit') . '</a></li>
		                                <li><a class="dropdown-item" href="#" onclick="confirm_modal(&#39;' . site_url('admin/instructors/delete/' . $instructor['id']) . '&#39;);">' . get_phrase('delete') . '</a></li>
		                            </ul>
		                        </div>';

            $nestedData['key']              = ++$key;
            $nestedData['photo']            = $photo;
            $nestedData['name']             = $name;
            $nestedData['email']            = $email;
            $nestedData['phone']            = $instructor['phone'];
            $nestedData['enrolled_courses'] = $enrolled_courses_title;
            $nestedData['action']           = $action . '<script>$("a, i").tooltip();</script>';
            $data[]                         = $nestedData;
        endforeach;

        $this->tq_dt_respond($data, $total_number_of_row, $filtered_number_of_row);
    }

    public function add_shortcut_student()
    {
        /* TQ-POST-ONLY — نقطة فعل لا تستدعى بـGET: الزحف واستباق
           التحميل ينفذانها بلا قصد. */
        if (strtolower($this->input->method()) !== 'post') {
            show_error(get_phrase('Method not allowed'), 405);
        }

        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }

        // CHECK ACCESS PERMISSION
        check_permission('user');
        check_permission('student');

        $is_instructor = 0;
        echo $this->user_model->add_shortcut_user($is_instructor);
    }

    public function user_form($param1 = "", $param2 = "")
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }

        // CHECK ACCESS PERMISSION
        check_permission('user');
        check_permission('student');

        if ($param1 == 'add_user_form') {
            $page_data['page_name']  = 'user_add';
            $page_data['page_title'] = get_phrase('student_add');
            $this->load->view('backend/index', $page_data);
        } elseif ($param1 == 'edit_user_form') {
            $page_data['page_name']  = 'user_edit';
            $page_data['user_id']    = $param2;
            $page_data['page_title'] = get_phrase('student_edit');
            $this->load->view('backend/index', $page_data);
        }
    }

    public function enrol_history($param1 = "")
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }

        // CHECK ACCESS PERMISSION
        check_permission('enrolment');

        if ($param1 != "") {
            $date_range                   = $this->input->get('date_range');
            $date_range                   = explode(" - ", $date_range);
            $page_data['timestamp_start'] = strtotime($date_range[0]);
            $page_data['timestamp_end']   = strtotime($date_range[1]);
        } else {
            $first_day_of_month           = "1 " . date("M") . " " . date("Y") . ' 00:00:00';
            $last_day_of_month            = date("t") . " " . date("M") . " " . date("Y") . ' 23:59:59';
            $page_data['timestamp_start'] = strtotime($first_day_of_month);
            $page_data['timestamp_end']   = strtotime($last_day_of_month);
        }
        $page_data['page_name']     = 'enrol_history';
        $page_data['enrol_history'] = $this->crud_model->enrol_history_by_date_range($page_data['timestamp_start'], $page_data['timestamp_end']);
        $page_data['page_title']    = get_phrase('enrol_history');
        $this->load->view('backend/index', $page_data);
    }

    public function enrol_student($param1 = "")
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }

        // CHECK ACCESS PERMISSION
        check_permission('enrolment');

        if ($param1 == 'enrol') {
            $this->crud_model->enrol_a_student_manually();
            redirect(site_url('admin/enrol_history'), 'refresh');
        }
        $page_data['page_name']  = 'enrol_student';
        $page_data['page_title'] = get_phrase('course_enrolment');
        $this->load->view('backend/index', $page_data);
    }

    public function shortcut_enrol_student()
    {
        /* TQ-POST-ONLY — نقطة فعل لا تستدعى بـGET: الزحف واستباق
           التحميل ينفذانها بلا قصد. */
        if (strtolower($this->input->method()) !== 'post') {
            show_error(get_phrase('Method not allowed'), 405);
        }

        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }

        // CHECK ACCESS PERMISSION
        check_permission('enrolment');

        echo $this->crud_model->shortcut_enrol_a_student_manually();
    }




    public function enrol_history_delete($param1 = "")
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }

        // CHECK ACCESS PERMISSION
        check_permission('enrolment');

        $this->crud_model->delete_enrol_history($param1);
        $this->session->set_flashdata('flash_message', get_phrase('data_deleted_successfully'));
        redirect(site_url('admin/enrol_history'), 'refresh');
    }


    public function system_settings($param1 = "")
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }

        // CHECK ACCESS PERMISSION
        check_permission('settings');

        if ($param1 == 'system_update') {
            $this->crud_model->update_system_settings();
            $this->session->set_flashdata('flash_message', get_phrase('system_settings_updated'));
            redirect(site_url('admin/system_settings'), 'refresh');
        }

        if ($param1 == 'logo_upload') {
            move_uploaded_file($_FILES['logo']['tmp_name'], 'assets/backend/logo.png');
            $this->session->set_flashdata('flash_message', get_phrase('backend_logo_updated'));
            redirect(site_url('admin/system_settings'), 'refresh');
        }

        if ($param1 == 'favicon_upload') {
            move_uploaded_file($_FILES['favicon']['tmp_name'], 'assets/favicon.png');
            $this->session->set_flashdata('flash_message', get_phrase('favicon_updated'));
            redirect(site_url('admin/system_settings'), 'refresh');
        }

        $page_data['languages']  = $this->crud_model->get_all_languages();
        $page_data['page_name']  = 'system_settings';
        $page_data['page_title'] = get_phrase('system_settings');
        $this->load->view('backend/index', $page_data);
    }

    public function frontend_settings($param1 = "", $param2 = "")
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }

        // CHECK ACCESS PERMISSION
        check_permission('settings');

        if ($param1 == 'frontend_update') {
            $this->crud_model->update_frontend_settings();
            $this->session->set_flashdata('flash_message', get_phrase('frontend_settings_updated'));
            redirect(site_url('admin/frontend_settings?tab=frontendsettings'), 'refresh');
        }

        if ($param1 == 'recaptcha_update') {
            $this->crud_model->update_recaptcha_settings();
            $this->session->set_flashdata('flash_message', get_phrase('recaptcha_settings_updated'));
            redirect(site_url('admin/frontend_settings?tab=recaptcha'), 'refresh');
        }

        if ($param1 == 'banner_image_update') {
            $this->crud_model->update_frontend_banner();
            $this->session->set_flashdata('flash_message', get_phrase('banner_image_update'));
            redirect(site_url('admin/frontend_settings?tab=logo_and_images'), 'refresh');
        }
        if ($param1 == 'light_logo') {
            $this->crud_model->update_light_logo();
            $this->session->set_flashdata('flash_message', get_phrase('logo_updated'));
            redirect(site_url('admin/frontend_settings?tab=logo_and_images'), 'refresh');
        }
        if ($param1 == 'dark_logo') {
            $this->crud_model->update_dark_logo();
            $this->session->set_flashdata('flash_message', get_phrase('logo_updated'));
            redirect(site_url('admin/frontend_settings?tab=logo_and_images'), 'refresh');
        }
        if ($param1 == 'small_logo') {
            $this->crud_model->update_small_logo();
            $this->session->set_flashdata('flash_message', get_phrase('logo_updated'));
            redirect(site_url('admin/frontend_settings?tab=logo_and_images'), 'refresh');
        }
        if ($param1 == 'favicon') {
            $this->crud_model->update_favicon();
            $this->session->set_flashdata('flash_message', get_phrase('favicon_updated'));
            redirect(site_url('admin/frontend_settings?tab=logo_and_images'), 'refresh');
        }

        if ($param1 == 'motivational_speech') {
            $this->crud_model->update_motivational_speech();
            $this->session->set_flashdata('flash_message', get_phrase('Motivational speech updated successfully'));
            redirect(site_url('admin/frontend_settings?tab=frontendsettings'), 'refresh');
        }

        if ($param1 == 'website_faq') {
            $this->crud_model->update_website_faq();
            $this->session->set_flashdata('flash_message', get_phrase('Website FAQS updated successfully'));
            redirect(site_url('admin/frontend_settings?tab=websitefaqs'), 'refresh');
        }

        if ($param1 == 'contact_info') {
            $this->crud_model->update_contact_info();
            $this->session->set_flashdata('flash_message', get_phrase('Contact information updated successfully'));
            redirect(site_url('admin/frontend_settings?tab=contact_information'), 'refresh');
        }

        if ($param1 == 'custom_codes') {
            $this->crud_model->update_custom_codes();
            $this->session->set_flashdata('flash_message', get_phrase('Your custom codes updated successfully'));
            redirect(site_url('admin/frontend_settings?tab=custom_codes'), 'refresh');
        }

        if ($param1 == 'home_page_settings') {
            echo $this->crud_model->update_home_page_settings($param2);
            return;
        }

        if ($param1 == 'water_mark') {
            $this->crud_model->update_water_mark();
            $this->session->set_flashdata('flash_message', get_phrase('video water marks updated successfully'));
            redirect(site_url('admin/frontend_settings?tab=water_mark'), 'refresh');
        }

        if ($param1 == 'review_store') {
            $this->crud_model->review_store();
            $this->session->set_flashdata('flash_message', get_phrase('Review  Added successfully'));
            redirect(site_url('admin/frontend_settings?tab=review'), 'refresh');
        }

        if ($param1 == 'review_update') {
            $this->crud_model->review_update();
            $this->session->set_flashdata('flash_message', get_phrase('Review  Update successfully'));
            redirect(site_url('admin/frontend_settings?tab=review'), 'refresh');
        }

        $page_data['page_name']  = 'frontend_settings';
        $page_data['page_title'] = get_phrase('frontend_settings');
        $this->load->view('backend/index', $page_data);
    }
    public function payment_settings($param1 = "")
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }

        // CHECK ACCESS PERMISSION
        check_permission('settings');

        if ($param1 == 'system_currency') {
            $this->crud_model->update_system_currency();
            redirect(site_url('admin/payment_settings'), 'refresh');
        }

        if (isset($_POST['identifier'])) {
            $this->crud_model->update_payment_settings();
            redirect(site_url('admin/payment_settings'), 'refresh');
        }

        $page_data['payment_gateways'] = $this->crud_model->get_payment_gateways()->result_array();
        $page_data['page_name']        = 'payment_settings';
        $page_data['page_title']       = get_phrase('payment_settings');
        $this->load->view('backend/index', $page_data);
    }

    public function notification_settings($param1 = "", $param2 = "", $param3 = "")
    {
        // بوابة الصلاحية — كانت غائبة
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'));
        }
        check_permission('settings');

        if ($param1 == 'smtp_settings') {
            $this->crud_model->update_smtp_settings();
            $this->session->set_flashdata('flash_message', get_phrase('smtp_settings_updated_successfully'));
            redirect(site_url('admin/notification_settings'), 'refresh');
        }

        if ($param1 == 'notification_enable_diable') {
            echo $this->crud_model->notification_enable_diable();
            return;
        }

        if (isset($_GET['tab'])) {
            $page_data['tab'] = $_GET['tab'];
        } else {
            $page_data['tab'] = 'smtp-settings';
        }

        $page_data['page_name']  = 'notification_settings';
        $page_data['page_title'] = get_phrase('Notification settings');
        $this->load->view('backend/index', $page_data);
    }

    public function edit_email_template($id = "", $param2 = "")
    {

        if ($param2 == 'update') {
            $data['subject']  = json_encode($this->input->post('subject'));
            $data['template'] = json_encode($this->input->post('template'));
            $this->db->where('id', $id)->update('notification_settings', $data);
            $this->session->set_flashdata('flash_message', get_phrase('Email template updated successfully'));
            redirect(site_url('admin/notification_settings?tab=email-template'), 'refresh');
        }
        $page_data['notification'] = $this->db->where('id', $id)->get('notification_settings')->row_array();
        $this->load->view('backend/admin/edit_email_template', $page_data);
    }


    public function instructor_settings($param1 = "")
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }

        // CHECK ACCESS PERMISSION
        check_permission('user');
        check_permission('instructor');

        if ($param1 == 'update') {
            $this->crud_model->update_instructor_settings();
            $this->session->set_flashdata('flash_message', get_phrase('instructor_settings_updated'));
            redirect(site_url('admin/instructor_settings'), 'refresh');
        }

        $page_data['page_name']  = 'instructor_settings';
        $page_data['page_title'] = get_phrase('instructor_settings');
        $this->load->view('backend/index', $page_data);
    }



    public function courses()
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }

        // CHECK ACCESS PERMISSION
        check_permission('course');

        $page_data['selected_category_id']   = isset($_GET['category_id']) ? $_GET['category_id'] : "all";
        $page_data['selected_instructor_id'] = isset($_GET['instructor_id']) ? $_GET['instructor_id'] : "all";
        $page_data['selected_price']         = isset($_GET['price']) ? $_GET['price'] : "all";
        $page_data['selected_status']        = isset($_GET['status']) ? $_GET['status'] : "all";
        $page_data['search_term']            = trim((string) $this->input->get('q', true));

        $page_data['page_name']  = 'courses-server-side';
        $page_data['categories'] = $this->crud_model->get_categories();
        /* منتقي المعلم كان يملأ بـselect2 عبر `get_select2_instructor_data`،
           و select2 غير محمل في اللوحة (انظر TQ-SELECT2-GONE) — فالمنتقي
           يظهر بخيار «الجميع» وحده ولا يمكن الترشيح بمعلم إطلاقا.
           والمعلمون هنا عشرات لا آلاف، فيملأ من الخادم مرة. */
        $page_data['instructors'] = $this->db->select('id, first_name, last_name, email')
                                             ->where('is_instructor', 1)
                                             ->order_by('first_name', 'ASC')
                                             ->get('users')->result_array();

        /**
         * صفوف الجدول تحضر هنا لا في نداء AJAX.
         *
         * TQ-DT-GONE — كان الجدول يبنى بـDataTables من جانب الخادم:
         * `POST admin/get_courses` يرد JSON فيرسمه القالب. وكلفة ذلك
         * ثلاثة أشياء دفعت كلها:
         *
         * ١ — **أي تعثر يخرج نافذة `alert()` بيضاء** نصها بالإنجليزية
         *     «DataTables warning … Ajax error» فوق اللوحة، تحجب الخيط
         *     الرئيسي حتى تغلق. وهي ما تراه هذه الشاشة اليوم: تحذير PHP
         *     واحد يسبق الـJSON فيسقط الجدول كله ولا يبقى منه صف.
         * ٢ — **الترشيح لا يصل إلى الرابط.** المرشحات ترسل في جسم POST،
         *     فصفحة الدورات المعلقة لا تحفظ ولا ترسل ولا يعود إليها زر
         *     الرجوع.
         * ٣ — **مظهر من منتج آخر.** صف الطول والبحث والترقيم أربع كتل
         *     من قالب Hyper وسط شاشة مبنية بهوية تقدر.
         *
         * والصفحة الواحدة عشرون صفا، فالعرض من الخادم أرخص من الرحلتين.
         */
        $tq_per  = 20;
        $tq_page = max(1, (int) $this->input->get('page'));

        /** المرشحات تكتب مرة وتطبق على عد الصفوف وعلى جلبها معا. */
        $tq_scope = function () use ($page_data) {
            if ($page_data['search_term'] !== '') {
                $this->db->group_start()
                         ->like('title', $page_data['search_term'])
                         ->or_like('short_description', $page_data['search_term'])
                         ->group_end();
            }
            /* المرحلة تطابق العمودين: `sub_category_id` هو ما اختير،
               و`category_id` أبوه. فالترشيح بمرحلة يلتقط كورساتها
               وكورسات تصنيفاتها الفرعية معا — وكان يطابق الأول وحده،
               فيرجع فارغا لكل مرحلة لها أبناء. */
            if ($page_data['selected_category_id'] !== 'all') {
                $cat = (int) $page_data['selected_category_id'];
                $this->db->group_start()
                         ->where('sub_category_id', $cat)
                         ->or_where('category_id', $cat)
                         ->group_end();
            }
            if ($page_data['selected_instructor_id'] !== 'all') {
                $this->db->where('creator', (int) $page_data['selected_instructor_id']);
            }
            if ($page_data['selected_status'] !== 'all') {
                $this->db->where('status', $page_data['selected_status']);
            }
            /* `is_free_course` ثلاثي القيم في هذه القاعدة: `1` و`0`
               و`NULL` — والأخيرتان تعنيان «مدفوع». وكان «مدفوع» يقيد
               بـ`IS NULL` وحدها، فيسقط كل كورس كتب صفره صراحة. */
            if ($page_data['selected_price'] === 'free') {
                $this->db->where('is_free_course', 1);
            } elseif ($page_data['selected_price'] === 'paid') {
                $this->db->group_start()
                         ->where('is_free_course IS NULL', null, false)
                         ->or_where('is_free_course !=', 1)
                         ->group_end();
            }
        };

        $tq_scope();
        $tq_total = (int) $this->db->count_all_results('course');

        $tq_pages = max(1, (int) ceil($tq_total / $tq_per));
        $tq_page  = min($tq_page, $tq_pages);

        $tq_scope();
        $page_data['courses'] = $this->db->order_by('id', 'DESC')
                                         ->limit($tq_per, ($tq_page - 1) * $tq_per)
                                         ->get('course')->result_array();

        $page_data['total_courses'] = $tq_total;
        $page_data['page_no']       = $tq_page;
        $page_data['page_count']    = $tq_pages;
        $page_data['per_page']      = $tq_per;

        $page_data['page_title'] = get_phrase('courses');
        $this->load->view('backend/index', $page_data);
    }

    /**
     * بيانات جدول الدورات — يقرؤها DataTables من جانب الخادم.
     *
     * TQ-DT-JSON — الشاشة كانت ترد «DataTables warning … Ajax error» في
     * نافذة تنبيه بيضاء فوق اللوحة، وسبب ذلك ليس في المتصفح:
     *
     * ١ — **تحذير PHP يسبق الـJSON.** ست دورات في هذه القاعدة
     *     `sub_category_id = 0`، و`get_category_details_by_id(0)` لا ترد
     *     صفا، فـ`$category_details['name']` تصير قراءة فهرس من `null`.
     *     وهي في PHP 8.2 **تحذير** لا خطأ — يطبع في المخرجات لأن البيئة
     *     المحلية `display_errors = 1`، فيسبق أول قوس من الـJSON فيرفض
     *     المحلل الرد كله. وعلى الخادم الحي لا يطبع، فالجدول يظهر بخانة
     *     تصنيف فارغة ولا يشكو أحد — وهو العطل نفسه صامتا.
     *
     * ٢ — **`order[0]` مفترض لا مفحوص.** أي نداء بلا ترتيب (وهو ما ترسله
     *     `ajax.reload()` أحيانا) يرمي على فهرس غير موجود.
     *
     * ٣ — **الصلاحية غير مفحوصة.** `admin/courses` تفحص `check_permission`
     *     ولا تفحصها هذه، فمسؤول ممنوع من وحدة الدورات يقرؤها كاملة من
     *     المسار المباشر. (والدخول نفسه مفحوص في الباني.)
     *
     * والرد يخرج الآن بـ`application/json` صريحا وبمخرج نظيف: أي تحذير
     * تسرب من دالة أبعد لا يخلط بالـJSON.
     */
    public function get_courses()
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }
        check_permission('course');

        $data = [];
        //mentioned all with colum of database table that related with html table
        $columns = ['id', 'title', 'sub_category_id', 'section', 'id', 'status', 'price', 'id'];

        // Filter portion
        $category_id   = $this->input->post('selected_category_id');
        $instructor_id = $this->input->post('selected_instructor_id');
        $price         = $this->input->post('selected_price');
        $status        = $this->input->post('selected_status');

        list($limit, $start, $column_index, $dir, $search) = $this->tq_dt_request($columns);

        $total_number_of_row = $this->crud_model->get_courses()->num_rows();

        //FILTERED DATA
        $this->db->select('*');
        if (! empty($search)) {
            $this->db->group_start();
            $this->db->like('title', $search);
            $this->db->or_like('status', $search);
            $this->db->or_like('price', $search);
            $this->db->or_like('discounted_price', $search);
            $this->db->group_end();
        }
        if (! empty($category_id) && $category_id != 'all') {
            $this->db->where('sub_category_id', $category_id);
        }
        if (! empty($instructor_id) && $instructor_id != 'all') {
            $this->db->where('creator', $instructor_id);
        }
        if (! empty($price) && $price != 'all') {
            if ($price == 'free') {
                $this->db->where('is_free_course', 1);
            } elseif ($price == 'paid') {
                $this->db->where('is_free_course', null);
            }
        }

        if (! empty($status) && $status != 'all') {
            $this->db->group_start();
            $this->db->where('status', $status);
            $this->db->group_end();
        }

        $this->db->limit($limit, $start);
        $this->db->order_by($column_index, $dir);
        $courses = $this->db->get('course')->result_array();

        //WITHOUT FILTERED DATA
        $this->db->select('*');
        if (! empty($search)) {
            $this->db->group_start();
            $this->db->like('title', $search);
            $this->db->or_like('status', $search);
            $this->db->or_like('price', $search);
            $this->db->or_like('discounted_price', $search);
            $this->db->group_end();
        }
        if (! empty($category_id) && $category_id != 'all') {
            $this->db->where('sub_category_id', $category_id);
        }
        if (! empty($instructor_id) && $instructor_id != 'all') {
            $this->db->where('creator', $instructor_id);
        }
        if (! empty($price) && $price != 'all') {
            if ($price == 'free') {
                $this->db->where('is_free_course', 1);
            } elseif ($price == 'paid') {
                $this->db->where('is_free_course', null);
            }
        }
        if (! empty($status) && $status != 'all') {
            $this->db->group_start();
            $this->db->where('status', $status);
            $this->db->group_end();
        }
        $filtered_number_of_row = $this->db->get('course')->num_rows();

        // Fetch the data and make it as JSON format and return it.
        if (! empty($courses)) {
            foreach ($courses as $key => $row) {
                /* الصف الغائب يرد `null` لا مصفوفة فارغة: دورة صنفها صفر
                   — وهي ست دورات هنا — كانت تسقط الرد كله. */
                $category_details   = $this->crud_model->get_category_details_by_id($row['sub_category_id'])->row_array();
                if (!is_array($category_details)) {
                    $category_details = array('name' => 'بلا تصنيف');
                }
                $sections           = $this->crud_model->get_section('course', $row['id']);
                $lessons            = $this->crud_model->get_lessons('course', $row['id']);
                $enroll_history     = $this->crud_model->enrol_history($row['id']);

                $status_badge = "badge-success-lighten";
                if ($row['status'] == 'pending') {
                    $status_badge = "badge-danger-lighten";
                } elseif ($row['status'] == 'draft') {
                    $status_badge = "badge-dark-lighten";
                } elseif ($row['status'] == 'private') {
                    $status_badge = "badge-dark";
                } elseif ($row['status'] == 'upcoming') {
                    $status_badge = "badge-warning-lighten";
                }

                $price_badge = "badge-dark-lighten";
                $price       = 0;
                if ($row['is_free_course'] == null) {
                    if ($row['discount_flag'] == 1) {
                        $price = currency($row['discounted_price']);
                    } else {
                        $price = currency($row['price']);
                    }
                } elseif ($row['is_free_course'] == 1) {
                    $price_badge = "badge-success-lighten";
                    $price       = get_phrase('free');
                }

                $price_field = '<span class="badge ' . $price_badge . '">' . $price . '</span>';
                if ($row['expiry_period'] > 0) {
                    $price_field .= '<p class="text-12">' . '( ' . $row['expiry_period'] . ' ' . get_phrase('Months') . ' )' . '</p>';
                } else {
                    $price_field .= '<p class="text-12">' . '( ' . get_phrase('Lifetime') . ' )' . '</p>';
                }

                $view_course_on_frontend_url = site_url('home/course/' . rawurlencode(slugify($row['title'])) . '/' . $row['id']);
                $go_to_course_playing_page   = site_url('home/lesson/' . rawurlencode(slugify($row['title'])) . '/' . $row['id']);
                $edit_this_course_url        = site_url('admin/course_form/course_edit/' . $row['id']);
                $duplicate_this_course_url   = site_url('admin/course_form/course_duplicate/' . $row['id']);
                $section_and_lesson_url      = site_url('admin/course_form/course_edit/' . $row['id']);
                $academic_progress_url       = site_url('admin/course_form/course_edit/' . $row['id'] . '?tab=academic_progress');

                if ($row['status'] == 'active') {
                    $course_status_changing_message = get_phrase('mark_as_pending');
                    if ($row['user_id'] != $this->session->userdata('user_id')) {
                        $course_status_changing_action = "showAjaxModal('" . site_url('modal/popup/mail_on_course_status_changing_modal/pending/' . $row['id'] . '/' . $category_id . '/' . $instructor_id . '/all/' . $status) . "', '" . $course_status_changing_message . "')";
                    } else {
                        $course_status_changing_action = "confirm_modal('" . site_url('admin/change_course_status_for_admin/pending/' . $row['id'] . '/' . $category_id . '/' . $instructor_id . '/all/' . $status) . "')";
                    }
                } else {
                    $course_status_changing_message = get_phrase('mark_as_active');
                    if ($row['user_id'] != $this->session->userdata('user_id')) {
                        $course_status_changing_action = "showAjaxModal('" . site_url('modal/popup/mail_on_course_status_changing_modal/active/' . $row['id'] . '/' . $category_id . '/' . $instructor_id . '/all/' . $status) . "', '" . $course_status_changing_message . "')";
                    } else {
                        $course_status_changing_action = "confirm_modal('" . site_url('admin/change_course_status_for_admin/active/' . $row['id'] . '/' . $category_id . '/' . $instructor_id . '/all/' . $status) . "')";
                    }
                }

                $delete_course_url = "confirm_modal('" . site_url('admin/course_actions/delete/' . $row['id']) . "')";

                if ($row['course_type'] == 'general') {
                    $section_and_lesson_menu = '<li><a class="dropdown-item" href="' . $section_and_lesson_url . '">' . get_phrase("section_and_lesson") . '</a></li>';
                } else {
                    $section_and_lesson_menu = "";
                }

                $course_academic_progress_menu = '<li><a class="dropdown-item" href="' . $academic_progress_url . '">' . get_phrase("Academic progress") . '</a></li>';

                $course_edit_menu = '<li><a class="dropdown-item" href="' . $edit_this_course_url . '">' . get_phrase("edit_this_course") . '</a></li>';

                $course_duplicate_menu = '<li><a class="dropdown-item" href="' . $duplicate_this_course_url . '">' . get_phrase("duplicate_this_course") . '</a></li>';

                $course_delete_menu = '<li><a class="dropdown-item" href="javascript:;" onclick="' . $delete_course_url . '">' . get_phrase("delete") . '</a></li>';

                $action = '
                <div class="dropright dropright">
                <button type="button" class="btn btn-sm btn-outline-primary btn-rounded btn-icon" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="mdi mdi-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="' . $view_course_on_frontend_url . '" target="_blank">' . get_phrase("view_course_on_frontend") . '</a></li>
                <li><a class="dropdown-item" href="' . $go_to_course_playing_page . '" target="_blank">' . get_phrase("go_to_course_playing_page") . '</a></li>
                ' . $course_academic_progress_menu . $course_edit_menu . $course_duplicate_menu . $section_and_lesson_menu . '
                <li><a class="dropdown-item" href="javascript:;" onclick="' . $course_status_changing_action . '">' . $course_status_changing_message . '</a></li>
                ' . $course_delete_menu . '
                </ul>
                </div>
                ';

                $nestedData['#'] = $key + 1;

                /* الأسماء تجمع ولا يطغى آخرها: كانت الحلقة **تسند** لا
                   تضيف، فدورة بثلاثة معلمين تعرض اسم آخرهم وحده.
                   والحساب المحذوف يرد `null` — فيتخطى لا يوقف الرد. */
                $instructor_names = array();
                foreach ($this->crud_model->get_course_instructors_id($row['id']) as $one_instructor_id) {
                    $multi_instructor = $this->user_model->get_all_user($one_instructor_id)->row_array();
                    if (!is_array($multi_instructor)) {
                        continue;
                    }
                    $name = trim($multi_instructor['first_name'] . ' ' . $multi_instructor['last_name']);
                    if ($name !== '') {
                        $instructor_names[] = $name;
                    }
                }
                $instructor_names = $instructor_names ? implode('، ', $instructor_names) : '—';

                $nestedData['title'] = '<strong><a href="' . site_url('admin/course_form/course_edit/' . $row['id']) . '">' . $row['title'] . '</a></strong><br>
                <small class="text-muted">' . get_phrase('instructor') . ': <b>' . $instructor_names . '</b></small>';

                $nestedData['category'] = '<span class="badge badge-dark-lighten">' . $category_details['name'] . '</span>';

                if ($row['course_type'] == 'scorm') {
                    $nestedData['lesson_and_section'] = '<span class="badge badge-info-lighten">' . get_phrase('scorm_course') . '</span>';
                } elseif ($row['course_type'] == 'h5p') {
                    $nestedData['lesson_and_section'] = '<span class="badge badge-info-lighten">' . get_phrase('h5p_course') . '</span>';
                } elseif ($row['course_type'] == 'general') {
                    $nestedData['lesson_and_section'] = '
                    <small class="text-muted"><b>' . get_phrase('Section') . '</b>: ' . $sections->num_rows() . '</small><br>
                    <small class="text-muted"><b>' . get_phrase('Lesson') . '</b>: ' . $lessons->num_rows() . '</small>';
                }

                $nestedData['enrolled_student'] = '<small class="text-muted"><b>' . get_phrase('Enrollments') . '</b>: ' . $enroll_history->num_rows() . '</small>';

                $nestedData['status'] = '<span class="badge ' . $status_badge . '">' . get_phrase($row['status']) . '</span>';

                $nestedData['price'] = $price_field;

                $nestedData['actions'] = $action;

                $nestedData['course_id'] = $row['id'];

                $data[] = $nestedData;
            }
        }

        $json_data = [
            "draw"            => intval($this->input->post('draw')),
            "recordsTotal"    => intval($total_number_of_row),
            "recordsFiltered" => intval($filtered_number_of_row),
            "data"            => $data,
        ];

        /* المخرج ينظف قبل الطباعة: أي تحذير أو مسافة تسربت من دالة أبعد
           تسبق أول قوس فتجعل الرد غير صالح — وهو ما يقرؤه DataTables
           «Ajax error» بلا أن يقول أين. */
        if (ob_get_length()) {
            @ob_clean();
        }
        $this->output
             ->set_content_type('application/json', 'utf-8')
             ->set_output(json_encode($json_data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * TQ-PENDING-MERGE — حولت إلى قائمة الدورات مرشحة بـ«معلقة».
     *
     * كان لها قالبها الخاص، وهو من **الجيل الأول** من Academy: بنية
     * Bootstrap 3 (`panel panel-primary` · `col-md-offset-2` · أيقونات
     * `entypo-`)، وفتات خبز تشير إلى `admin/dashboard`. أي أنه لو عرض
     * لخرج بمظهر منتج آخر داخل اللوحة.
     *
     * وكان يقرأ أعمدة قراءة تسقط في هذه البيانات: `$category_details['name']`
     * بلا فحص (وست دورات هنا تصنيفها صفر)، و`get_all_user($course['user_id'])`
     * بينما العمود يحمل أحيانا قائمة معرفات («147,289») فلا يطابق شيئا،
     * فيقرأ `['first_name']` من `null`.
     *
     * ولا رابط إليه من أي شاشة: بند «الكورسات» في الشريط يشير إلى
     * `admin/courses`، وهي تعرض المعلقة بمرشح الحالة وتغير حالتها من
     * قائمة الإجراءات. فشاشتان لعمل واحد، وإحداهما معطوبة.
     *
     * والتحويل لا الحذف: `admin/pending_courses` مكتوب في إشارات مرجعية.
     */
    public function pending_courses()
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }
        check_permission('course');

        redirect(site_url('admin/courses?category_id=all&status=pending&instructor_id=all&price=all'),
                 'location', 302);
    }

    public function course_actions($param1 = "", $param2 = "")
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }
        // CHECK ACCESS PERMISSION
        check_permission('course');

        if ($param1 == "add") {
            $course_id = $this->crud_model->add_course();
            $this->tq_sync_course_link($course_id);
            redirect(site_url('admin/course_form/course_edit/' . $course_id), 'refresh');
        } elseif ($param1 == 'add_shortcut') {
            echo $this->crud_model->add_shortcut_course();
        } elseif ($param1 == "edit") {

            $this->crud_model->update_course($param2);
            $this->tq_sync_course_link($param2);

            // CHECK IF LIVE CLASS ADDON EXISTS, ADD OR UPDATE IT TO ADDON MODEL
            if (addon_status('live-class')) {
                $this->load->model('addons/Liveclass_model', 'liveclass_model');
                $this->liveclass_model->update_live_class($param2);
            }

            // CHECK IF JITSI LIVE CLASS ADDON EXISTS, ADD OR UPDATE IT TO ADDON MODEL
            if (addon_status('jitsi-live-class')) {
                $this->load->model('addons/jitsi_liveclass_model', 'jitsi_liveclass_model');
                $this->jitsi_liveclass_model->update_live_class($param2);
            }

            redirect(site_url('admin/course_form/course_edit/' . $param2));
        } elseif ($param1 == 'delete') {

            /* TQ-GET-DESTROY — كان الحذف رابطا `<a href>`: أي أن **مجرد
               جلب العنوان يحذف الكورس ودروسه وتسجيلات طلابه**. والتأكيد
               كان في المتصفح وحده، فلا يقف أمام جالب مسبق ولا أمام رابط
               يفتح من سجل أو من زر رجوع. */
            if ($this->input->method(true) !== 'POST') show_404();

            $this->is_drafted_course($param2);

            /* البرنامج المرتبط ينزل إلى مسودة **قبل** حذف الكورس، وإلا
               بقي معروضا في «المواد والبرامج» بعنوان يفتح على لا شيء.
               ولا يحذف: قد تشير إليه بنود اشتراك قائمة في
               `subscription_items` بمعرفه. */
            $this->load->model('taqdar_course_link_model', 'tq_link_m');
            $this->tq_link_m->sync($param2, 0, 0);

            $this->crud_model->delete_course($param2);
            $this->session->set_flashdata('flash_message', get_phrase('حذف الكورس'));
            redirect(site_url('admin/courses'), 'refresh');
        }
    }

    /**
     * يوائم برنامج الكورس مع ما اختير له من صف ومادة.
     *
     * لا ينادى إلا حين يرسل النموذج `tq_link_sent`: تبويبات التحرير
     * ترسل حقولها وحدها، فحفظ «التسعير» لا يحمل صفا ولا مادة — ولو قرئت
     * منه لفهم غيابهما «احذف الربط».
     */
    private function tq_sync_course_link($course_id)
    {
        $course_id = (int) $course_id;
        if ($course_id <= 0) return;
        if ($this->input->post('tq_link_sent') === null
            && $this->input->post('tq_grade_id') === null) return;

        $this->load->model('taqdar_course_link_model', 'tq_link_m');
        $this->tq_link_m->sync($course_id,
            (int) $this->input->post('tq_grade_id'),
            (int) $this->input->post('tq_subject_id'));
    }

    public function course_form($param1 = "", $param2 = "")
    {

        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }

        // CHECK ACCESS PERMISSION
        check_permission('course');

        if ($param1 == 'add_course') {

            $page_data['languages']  = $this->crud_model->get_all_languages();
            $page_data['categories'] = $this->crud_model->get_categories();
            $page_data['page_name']  = 'course_add';
            $page_data['page_title'] = get_phrase('add_course');
            $this->load->view('backend/index', $page_data);
        } elseif ($param1 == 'add_course_shortcut') {
            $page_data['languages']  = $this->crud_model->get_all_languages();
            $page_data['categories'] = $this->crud_model->get_categories();
            $this->load->view('backend/admin/course_add_shortcut', $page_data);
        } elseif ($param1 == 'course_edit') {

            $this->is_drafted_course($param2);
            $page_data['page_name']  = 'course_edit';
            $page_data['course_id']  = $param2;
            $page_data['page_title'] = get_phrase('edit_course');
            $page_data['languages']  = $this->crud_model->get_all_languages();
            $page_data['categories'] = $this->crud_model->get_categories();
            $this->load->view('backend/index', $page_data);
        } elseif ($param1 == 'course_duplicate') {
            $this->duplicate_course($param2);
            $this->session->set_flashdata('flash_message', get_phrase('Course Duplicate Successfully'));
            redirect(site_url('admin/courses'), 'refresh');
        }
    }

    public function duplicate_course($id)
    {
        $course        = $this->db->where('id', $id)->get('course')->row_array();
        $max_course_id = $this->db->select_max('id')->get('course')->row_array();
        $course['id']  = $max_course_id['id'] + 1;
        $this->db->insert('course', $course);
    }

    private function is_drafted_course($course_id)
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }
        $course_details = $this->crud_model->get_course_by_id($course_id)->row_array();
        // الدورة غير موجودة (رقم غير صحيح أو حذفت) -> حالة فارغة مفهومة بدل الانهيار
        if (empty($course_details)) {
            $this->session->set_flashdata('error_message', get_phrase('لا توجد دورة بهذا الرقم'));
            redirect(site_url('admin/courses'), 'refresh');
        }
        if ($course_details['status'] == 'draft') {
            $this->session->set_flashdata('error_message', get_phrase('you_do_not_have_right_to_access_this_course'));
            redirect(site_url('admin/courses'), 'refresh');
        }
    }

    public function change_course_status($updated_status = "")
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }

        $course_id     = $this->input->post('course_id');
        $category_id   = $this->input->post('category_id');
        $instructor_id = $this->input->post('instructor_id');
        $price         = $this->input->post('price');
        $status        = $this->input->post('status');
        if (isset($_POST['mail_subject']) && isset($_POST['mail_body'])) {
            $mail_subject = $this->input->post('mail_subject');
            $mail_body    = $this->input->post('mail_body');
            $this->email_model->send_mail_on_course_status_changing($course_id, $mail_subject, $mail_body);
        }
        $this->crud_model->change_course_status($updated_status, $course_id);
        $this->session->set_flashdata('flash_message', get_phrase('course_status_updated'));
        //redirect(site_url('admin/courses?category_id=' . $category_id . '&status=' . $status . '&instructor_id=' . $instructor_id . '&price=' . $price), 'refresh');
        redirect($_SERVER['HTTP_REFERER']);
    }

    public function change_course_status_for_admin($updated_status = "", $course_id = "", $category_id = "", $status = "", $instructor_id = "", $price = "")
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }
        check_permission('course');

        /* تغيير الظهور في الموقع العام كتابة، فلا يجرى بجلب رابط —
           انظر TQ-GET-DESTROY في `course_actions`. */
        if ($this->input->method(true) !== 'POST') show_404();

        $this->crud_model->change_course_status($updated_status, $course_id);
        /* الحالة تنتقل إلى البرنامج: كورس أوقف نشره وبرنامجه `published`
           يبقى معروضا في الكتالوج، فيضغطه الزائر ويجد صفحة لا تفتح. */
        $this->load->model('taqdar_course_link_model', 'tq_link_m');
        $link = $this->tq_link_m->link_of($course_id);
        if ($link['path_id'] > 0) {
            $this->tq_link_m->sync($course_id, $link['grade_id'], $link['subject_id']);
        }
        $this->session->set_flashdata('flash_message', get_phrase('course_status_updated'));
        redirect(site_url('admin/courses?category_id=' . $category_id . '&status=' . $status . '&instructor_id=' . $instructor_id . '&price=' . $price), 'refresh');
    }

    public function sections($param1 = "", $param2 = "", $param3 = "")
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }

        // CHECK ACCESS PERMISSION
        check_permission('course');

        if ($param2 == 'add') {
            $this->crud_model->add_section($param1);
            $this->session->set_flashdata('flash_message', get_phrase('section_has_been_added_successfully'));
        } elseif ($param2 == 'edit') {
            $this->crud_model->edit_section($param3);
            $this->session->set_flashdata('flash_message', get_phrase('section_has_been_updated_successfully'));
        } elseif ($param2 == 'delete') {
            $this->crud_model->delete_section($param1, $param3);
            $this->session->set_flashdata('flash_message', get_phrase('section_has_been_deleted_successfully'));
        }
        redirect(site_url('admin/course_form/course_edit/' . $param1));
    }

    public function lessons($course_id = "", $param1 = "", $param2 = "")
    {
        // CHECK ACCESS PERMISSION
        check_permission('course');

        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }
        if ($param1 == 'add') {
            $response = $this->crud_model->add_lesson();
            echo $response;
            return;
        } elseif ($param1 == 'edit') {
            $response = $this->crud_model->edit_lesson($param2);
            echo $response;
            return;
        } elseif ($param1 == 'delete') {
            $this->crud_model->delete_lesson($param2);
            $this->session->set_flashdata('flash_message', get_phrase('lesson_has_been_deleted_successfully'));
            redirect('admin/course_form/course_edit/' . $course_id);
        } elseif ($param1 == 'filter') {
            redirect('admin/lessons/' . $this->input->post('course_id'));
        }
        $page_data['page_name']  = 'lessons';
        $page_data['lessons']    = $this->crud_model->get_lessons('course', $course_id);
        $page_data['course_id']  = $course_id;
        $page_data['page_title'] = get_phrase('lessons');
        $this->load->view('backend/index', $page_data);
    }

    public function watch_video($slugified_title = "", $lesson_id = "")
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }
        $lesson_details          = $this->crud_model->get_lessons('lesson', $lesson_id)->row_array();
        $page_data['provider']   = $lesson_details['video_type'];
        $page_data['video_url']  = $lesson_details['video_url'];
        $page_data['lesson_id']  = $lesson_id;
        $page_data['page_name']  = 'video_player';
        $page_data['page_title'] = get_phrase('video_player');
        $this->load->view('backend/index', $page_data);
    }

    // Language Functions
    public function manage_language($param1 = '', $param2 = '', $param3 = '')
    {

        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }

        // CHECK ACCESS PERMISSION
        check_permission('settings');

        if ($param1 == 'add_language') {
            $language = strtolower(trimmer($this->input->post('language')));
            if ($language == 'n-a') {
                $this->session->set_flashdata('error_message', get_phrase('language_name_can_not_be_empty_or_can_not_have_special_characters'));
                redirect(site_url('admin/manage_language'), 'refresh');
            }

            if (! $this->db->field_exists($language, 'language')) {
                $this->load->dbforge();
                $fields = [
                    $language => [
                        'type'      => 'TEXT',
                        'default'   => null,
                        'null'      => true,
                        'collation' => 'utf8_unicode_ci',
                    ],
                ];
                $this->dbforge->add_column('language', $fields);
            }

            saveDefaultJSONFile($language);
            $this->session->set_flashdata('flash_message', get_phrase('language_added_successfully'));
            redirect(site_url('admin/manage_language'), 'refresh');
        }
        if ($param1 == 'add_phrase') {
            $new_phrase = get_phrase($this->input->post('phrase'));
            $this->session->set_flashdata('flash_message', $new_phrase . ' ' . get_phrase('has_been_added_successfully'));
            redirect(site_url('admin/manage_language'), 'refresh');
        }

        if ($param1 == 'edit_phrase') {
            $page_data['edit_profile'] = $param2;
        }

        if ($param1 == 'delete_language') {
            if (file_exists('application/language/' . $param2 . '.json')) {
                unlink('application/language/' . $param2 . '.json');
                $this->session->set_flashdata('flash_message', get_phrase('language_deleted_successfully'));
                redirect(site_url('admin/manage_language'), 'refresh');
            }
        }
        $page_data['languages']  = $this->crud_model->get_all_languages();
        $page_data['page_name']  = 'manage_language';
        $page_data['page_title'] = get_phrase('multi_language_settings');
        $this->load->view('backend/index', $page_data);
    }

    public function update_phrase_with_ajax()
    {
        $current_editing_language = $this->input->post('currentEditingLanguage');
        $updatedValue             = $this->input->post('updatedValue');
        $key                      = $this->input->post('key');
        // بلا لغة أو مفتاح لا يبنى استعلام سليم -> نتجنب خطأ SQL بدل الانهيار
        if (empty($current_editing_language) || empty($key)) {
            echo '';
            return;
        }
        saveJSONFile($current_editing_language, $key, $updatedValue);
        echo $current_editing_language . ' ' . $key . ' ' . $updatedValue;
    }

    public function message($param1 = 'message_home', $param2 = '', $param3 = '')
    {
        if ($this->session->userdata('admin_login') != 1) {
            redirect(site_url('login'), 'refresh');
        }

        // CHECK ACCESS PERMISSION
        check_permission('messaging');

        if ($param1 == 'send_new') {
            $message_thread_code = $this->crud_model->send_new_private_message();
            $this->session->set_flashdata('flash_message', get_phrase('message_sent'));
            redirect(site_url('admin/message/message_read/' . $message_thread_code), 'refresh');
        }

        if ($param1 == 'send_reply') {
            $this->crud_model->send_reply_message($param2); //$param2 = message_thread_code
            $this->session->set_flashdata('flash_message', get_phrase('message_sent'));
            redirect(site_url('admin/message/message_read/' . $param2), 'refresh');
        }

        if ($param1 == 'message_read') {
            $page_data['current_message_thread_code'] = $param2; // $param2 = message_thread_code
            $this->crud_model->mark_thread_messages_read($param2);
        }

        $page_data['message_inner_page_name'] = $param1;
        $page_data['page_name']               = 'message';
        $page_data['page_title']              = get_phrase('private_messaging');
        $this->load->view('backend/index', $page_data);
    }

    /******MANAGE OWN PROFILE AND CHANGE PASSWORD***/
    public function manage_profile($param1 = '', $param2 = '', $param3 = '')
    {
        if ($this->session->userdata('admin_login') != 1) {
            redirect(site_url('login'), 'refresh');
        }

        if ($param1 == 'update_profile_info') {
            $this->user_model->edit_user($param2);
            redirect(site_url('admin/manage_profile'), 'refresh');
        }
        if ($param1 == 'change_password') {
            $this->user_model->change_password($param2);
            redirect(site_url('admin/manage_profile'), 'refresh');
        }
        $page_data['page_name']  = 'manage_profile';
        $page_data['page_title'] = get_phrase('manage_profile');
        $page_data['edit_data']  = $this->db->get_where('users', [
            'id' => $this->session->userdata('user_id'),
        ])->result_array();
        $this->load->view('backend/index', $page_data);
    }


    // PAYPAL CHECKOUT ACTIONS


    // STRIPE CHECKOUT ACTIONS


    public function preview($course_id = '')
    {
        if ($this->session->userdata('admin_login') != 1) {
            redirect(site_url('login'), 'refresh');
        }

        $this->is_drafted_course($course_id);
        if ($course_id > 0) {
            $courses = $this->crud_model->get_course_by_id($course_id);
            if ($courses->num_rows() > 0) {
                $course_details = $courses->row_array();
                redirect(site_url('home/lesson/' . rawurlencode(slugify($course_details['title'])) . '/' . $course_details['id']), 'refresh');
            }
        }
        redirect(site_url('admin/courses'), 'refresh');
    }

    // Manage Quizes
    public function quizes($course_id = "", $action = "", $quiz_id = "")
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }

        // CHECK ACCESS PERMISSION
        check_permission('course');

        if ($action == 'add') {
            $this->crud_model->add_quiz($course_id);
            $this->session->set_flashdata('flash_message', get_phrase('quiz_has_been_added_successfully'));
        } elseif ($action == 'edit') {
            $this->crud_model->edit_quiz($quiz_id);
            $this->session->set_flashdata('flash_message', get_phrase('quiz_has_been_updated_successfully'));
        } elseif ($action == 'delete') {
            $this->crud_model->delete_section($course_id, $quiz_id);
            $this->session->set_flashdata('flash_message', get_phrase('quiz_has_been_deleted_successfully'));
        }
        redirect(site_url('admin/course_form/course_edit/' . $course_id));
    }

    // Manage Quize Questions
    public function quiz_questions($quiz_id = "", $action = "", $question_id = "")
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }
        $quiz_details = $this->crud_model->get_lessons('lesson', $quiz_id)->row_array();

        if ($action == 'add' || $action == 'edit') {
            echo $this->crud_model->manage_quiz_questions($quiz_id, $question_id, $action);
        } elseif ($action == 'delete') {
            $response = $this->crud_model->delete_quiz_question($question_id);
            $this->session->set_flashdata('flash_message', get_phrase('question_has_been_deleted'));
            redirect(site_url('admin/course_form/course_edit/' . $quiz_details['course_id']), 'refresh');
        }
    }

    // software about page



    //ADDON MANAGER PORTION STARTS HERE


    // INSTRUCTOR PAYOUT SECTION

    // ADMINS SECTION STARTS
    public function admins($param1 = "", $param2 = "")
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }

        // CHECK ACCESS PERMISSION
        check_permission('admin');

        if ($param1 == "add") {
            // CHECK ACCESS PERMISSION
            check_permission('admin');

            $this->user_model->add_user(false, true); // PROVIDING TRUE FOR INSTRUCTOR
            $this->tq_bootstrap_admin_permissions($this->input->post('email'));
            redirect(site_url('admin/admins'), 'refresh');
        } elseif ($param1 == "edit") {
            // CHECK ACCESS PERMISSION
            check_permission('admin');

            $this->user_model->edit_user($param2);
            redirect(site_url('admin/admins'), 'refresh');
        } elseif ($param1 == "delete") {
            // CHECK ACCESS PERMISSION
            check_permission('admin');

            $this->user_model->delete_user($param2);
            redirect(site_url('admin/admins'), 'refresh');
        }

        $page_data['page_name']  = 'admins';
        $page_data['page_title'] = get_phrase('admins');
        $page_data['admins']     = $this->user_model->get_admins()->result_array();
        $this->load->view('backend/index', $page_data);
    }

    public function admin_form($param1 = "", $param2 = "")
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }
        if ($param1 == 'add_admin_form') {
            // CHECK ACCESS PERMISSION
            check_permission('admin');

            $page_data['page_name']  = 'admin_add';
            $page_data['page_title'] = get_phrase('admin_add');
            $this->load->view('backend/index', $page_data);
        } elseif ($param1 == 'edit_admin_form') {
            // CHECK ACCESS PERMISSION
            check_permission('admin');

            $page_data['page_name']  = 'admin_edit';
            $page_data['user_id']    = $param2;
            $page_data['page_title'] = get_phrase('admin_edit');
            $this->load->view('backend/index', $page_data);
        }
    }

    public function permissions()
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }
        // CHECK ACCESS PERMISSION
        check_permission('admin');

        if (! isset($_GET['permission_assing_to']) || empty($_GET['permission_assing_to'])) {
            $this->session->set_flashdata('error_message', get_phrase('you_have_select_an_admin_first'));
            redirect(site_url('admin/admins'), 'refresh');
        }

        $page_data['permission_assing_to'] = $this->input->get('permission_assing_to');
        $user_details                      = $this->user_model->get_all_user($page_data['permission_assing_to']);
        if ($user_details->num_rows() == 0) {
            $this->session->set_flashdata('error_message', get_phrase('invalid_admin'));
            redirect(site_url('admin/admins'), 'refresh');
        } else {
            $user_details = $user_details->row_array();
            if ($user_details['role_id'] != 1) {
                $this->session->set_flashdata('error_message', get_phrase('invalid_admin'));
                redirect(site_url('admin/admins'), 'refresh');
            }
            if (is_root_admin($user_details['id'])) {
                $this->session->set_flashdata('error_message', get_phrase('you_can_not_set_permission_to_the_root_admin'));
                redirect(site_url('admin/admins'), 'refresh');
            }
        }

        $page_data['permission_assign_to'] = $user_details;
        $page_data['page_name']            = 'admin_permission';
        $page_data['page_title']           = get_phrase('assign_permission');
        $this->load->view('backend/index', $page_data);
    }

    // ASSIGN PERMISSION TO ADMIN
    public function assign_permission()
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }

        // CHECK ACCESS PERMISSION
        check_permission('admin');

        echo $this->user_model->assign_permission();
    }

    // REMOVING INSTRUCTOR FROM COURSE
    public function remove_an_instructor($course_id, $instructor_id)
    {
        // CHECK ACCESS PERMISSION
        check_permission('course');

        $course_details = $this->crud_model->get_course_by_id($course_id)->row_array();

        if ($course_details['creator'] == $instructor_id) {
            $this->session->set_flashdata('error_message', get_phrase('course_creator_can_be_removed'));
            redirect('admin/course_form/course_edit/' . $course_id);
        }

        if ($course_details['multi_instructor']) {
            $instructor_ids = explode(',', $course_details['user_id']);

            if (in_array($instructor_id, $instructor_ids)) {
                if (count($instructor_ids) > 1) {
                    if (($key = array_search($instructor_id, $instructor_ids)) !== false) {
                        unset($instructor_ids[$key]);

                        $data['user_id'] = implode(",", $instructor_ids);
                        $this->db->where('id', $course_id);
                        $this->db->update('course', $data);

                        $this->session->set_flashdata('flash_message', get_phrase('instructor_has_been_removed'));
                        if ($this->session->userdata('user_id') == $instructor_id) {
                            redirect('admin/courses/');
                        } else {
                            redirect('admin/course_form/course_edit/' . $course_id);
                        }
                    }
                } else {
                    $this->session->set_flashdata('error_message', get_phrase('a_course_should_have_at_least_one_instructor'));
                    redirect('admin/course_form/course_edit/' . $course_id);
                }
            } else {
                $this->session->set_flashdata('error_message', get_phrase('invalid_instructor_id'));
                redirect('admin/course_form/course_edit/' . $course_id);
            }
        } else {
            $this->session->set_flashdata('error_message', get_phrase('a_course_should_have_at_least_one_instructor'));
            redirect('admin/course_form/course_edit/' . $course_id);
        }
    }


    // ADMINS SECTION ENDS

    // AJAX PORTION
    // this function is responsible for managing multiple choice question
    public function quiz_fields_type_wize()
    {
        $page_data['question_type'] = $this->input->post('question_type');
        $this->load->view('backend/admin/quiz_fields_type_wize', $page_data);
    }

    public function ajax_get_sub_category($category_id)
    {
        $page_data['sub_categories'] = $this->crud_model->get_sub_categories($category_id);

        return $this->load->view('backend/admin/ajax_get_sub_category', $page_data);
    }

    public function ajax_get_section($course_id)
    {
        $page_data['sections'] = $this->crud_model->get_section('course', $course_id)->result_array();
        return $this->load->view('backend/admin/ajax_get_section', $page_data);
    }

    public function ajax_get_video_details()
    {
        $video_details = $this->video_model->getVideoDetails($_POST['video_url']);
        if (is_array($video_details)) {
            echo $video_details['duration'];
        }
    }
    /**
     * حراسة مسارات الفرز الثلاثة.
     *
     * كانت الثلاثة **بلا فحص دخول ولا صلاحية ولا طريقة**: أي زائر يرسل
     * `POST admin/ajax_sort_section` بمصفوفة معرفات فيعيد ترتيب أقسام
     * أي كورس في المنصة. وهي مسارات AJAX لا تظهر في قائمة، فلا شيء
     * يلفت إليها — وذلك ما يجعلها أخطر لا أهون.
     */
    private function tq_guard_ajax_write()
    {
        if ($this->input->method(true) !== 'POST') show_404();
        if ($this->session->userdata('admin_login') != true) {
            $this->output->set_status_header(403)->set_output('');
            exit;
        }
        check_permission('course');
    }

    /**
     * ملفات الدرس — الوجه الكاتب لجدول `resource_files`.
     *
     * كان الجدول يقرأ ولا يكتب: يعرضه الطالب في «المواد» و«المفضلة»،
     * ولا موضع في المستودع ينشئ فيه صفا. انظر TQ-FILES-404 في
     * [resource_files.php].
     */
    public function resource_file_add($lesson_id = "")
    {
        if ($this->session->userdata('admin_login') != true) redirect(site_url('login'), 'refresh');
        check_permission('course');
        if ($this->input->method(true) !== 'POST') show_404();

        $lesson = $this->db->select('id, course_id')->where('id', (int) $lesson_id)
                           ->get('lesson')->row_array();
        if (!$lesson) show_404();

        $back = site_url('admin/course_form/course_edit/' . (int) $lesson['course_id'] . '?tab=curriculum');

        if (!tq_uploaded('resource_file')) {
            $this->session->set_flashdata('error_message', 'لم يصل ملف. راجع حجمه وحدود الرفع.');
            redirect($back, 'refresh');
        }

        $dir = 'uploads/resource_files/';
        if (!is_dir(FCPATH . $dir)) mkdir(FCPATH . $dir, 0755, true);

        /* الاسم يولد ولا يؤخذ من المستخدم: اسم المرفوع قد يحمل مسارا أو
           امتدادا مزدوجا، والامتداد وحده هو ما يحتاج. */
        $ext  = strtolower(pathinfo($_FILES['resource_file']['name'], PATHINFO_EXTENSION));
        $ext  = preg_replace('/[^a-z0-9]/', '', $ext);
        $deny = array('php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phar',
                      'htaccess', 'exe', 'sh', 'bat', 'cgi', 'pl');
        if ($ext === '' || in_array($ext, $deny, true)) {
            $this->session->set_flashdata('error_message', 'صيغة الملف غير مقبولة.');
            redirect($back, 'refresh');
        }

        $name = 'rf' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (!move_uploaded_file($_FILES['resource_file']['tmp_name'], FCPATH . $dir . $name)) {
            $this->session->set_flashdata('error_message', 'تعذر حفظ الملف على الخادم.');
            redirect($back, 'refresh');
        }

        $title = trim((string) $this->input->post('title'));
        if ($title === '') $title = (string) $_FILES['resource_file']['name'];

        $this->db->insert('resource_files', array(
            'lesson_id'  => (int) $lesson['id'],
            'title'      => $title,
            'file_name'  => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ));

        $this->session->set_flashdata('flash_message', 'أضيف الملف إلى الدرس.');
        redirect($back, 'refresh');
    }

    public function resource_file_delete($file_id = "")
    {
        if ($this->session->userdata('admin_login') != true) redirect(site_url('login'), 'refresh');
        check_permission('course');
        if ($this->input->method(true) !== 'POST') show_404();

        $row = $this->db->where('id', (int) $file_id)->get('resource_files')->row_array();
        if (!$row) show_404();

        $lesson = $this->db->select('course_id')->where('id', (int) $row['lesson_id'])
                           ->get('lesson')->row_array();

        $path = FCPATH . 'uploads/resource_files/' . $row['file_name'];
        if ($row['file_name'] !== '' && is_file($path)) @unlink($path);

        $this->db->where('id', (int) $row['id'])->delete('resource_files');
        /* والمفضلات المعلقة عليه تحذف معه: قلب يشير إلى ملف غير موجود
           يعرض في «مفضلتي» صفا لا يفتح. */
        if ($this->db->table_exists('tq_favourites')) {
            $this->db->where('kind', 'material')->where('item_id', (int) $row['id'])
                     ->delete('tq_favourites');
        }

        $this->session->set_flashdata('flash_message', 'حذف الملف.');
        redirect(site_url('admin/course_form/course_edit/'
               . (int) ($lesson['course_id'] ?? 0) . '?tab=curriculum'), 'refresh');
    }

    public function ajax_sort_section()
    {
        $this->tq_guard_ajax_write();
        $section_json = $this->input->post('itemJSON');
        $this->crud_model->sort_section($section_json);
    }
    public function ajax_sort_lesson()
    {
        $this->tq_guard_ajax_write();
        $lesson_json = $this->input->post('itemJSON');
        $this->crud_model->sort_lesson($lesson_json);
    }
    public function ajax_sort_question()
    {
        $this->tq_guard_ajax_write();
        $question_json = $this->input->post('itemJSON');
        $this->crud_model->sort_question($question_json);
    }

    //Start blog
    /**
     * نموذجا قسم المدونة — صفحتان كاملتان لا نافذتان.
     *
     * كانا يحملان قالبهما عاريا بلا غلاف وبلا بوابة دخول ولا صلاحية:
     * `admin/add_blog_category` تفتح لأي زائر نموذجا يكتب في القاعدة.
     */
    public function add_blog_category()
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }
        check_permission('blog');

        $page_data['page_name']  = 'blog_category_add';
        $page_data['page_title'] = get_phrase('add_a_new_category');
        $this->load->view('backend/index', $page_data);
    }

    public function edit_blog_category($blog_category_id = "")
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }
        check_permission('blog');

        $page_data['blog_category'] = $this->crud_model->get_blog_categories($blog_category_id)->row_array();
        $page_data['page_name']     = 'blog_category_edit';
        $page_data['page_title']    = get_phrase('edit_category');
        $this->load->view('backend/index', $page_data);
    }

    public function blog_category($param1 = "", $param2 = "")
    {
        // بوابة الصلاحية — كانت غائبة
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'));
        }
        check_permission('blog');

        if ($param1 == 'add') {
            $response = $this->crud_model->add_blog_category();
            if ($response == true) {
                $this->session->set_flashdata('flash_message', get_phrase('blog_category_added_successfully'));
            } else {
                $this->session->set_flashdata('error_message', get_phrase('there_is_already_a_blog_with_this_name'));
            }
            redirect(site_url('admin/blog_category'), 'refresh');
        } elseif ($param1 == 'update') {
            $response = $this->crud_model->update_blog_category($param2);
            if ($response == true) {
                $this->session->set_flashdata('flash_message', get_phrase('blog_category_updated_successfully'));
            } else {
                $this->session->set_flashdata('error_message', get_phrase('there_is_already_a_blog_with_this_name'));
            }
            redirect(site_url('admin/blog_category'), 'refresh');
        } elseif ($param1 == 'delete') {
            $this->crud_model->delete_blog_category($param2);
            $this->session->set_flashdata('flash_message', get_phrase('blog_category_deleted_successfully'));
            redirect(site_url('admin/blog_category'), 'refresh');
        }
        $page_data['categories'] = $this->crud_model->get_blog_categories();
        $page_data['page_title'] = get_phrase('blog_category');
        $page_data['page_name']  = 'blog_category';
        $this->load->view('backend/index', $page_data);
    }

    public function add_blog()
    {
        $page_data['page_title'] = get_phrase('add_blog');
        $page_data['page_name']  = 'blog_add';
        $this->load->view('backend/index', $page_data);
    }

    public function edit_blog($blog_id = "")
    {
        $page_data['blog']       = $this->crud_model->get_blogs($blog_id)->row_array();
        $page_data['page_title'] = get_phrase('edit_blog');
        $page_data['page_name']  = 'blog_edit';
        $this->load->view('backend/index', $page_data);
    }

    public function blog($param1 = "", $param2 = "")
    {
        // بوابة الصلاحية — كانت غائبة
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'));
        }
        check_permission('blog');

        if ($param1 == 'add') {
            $this->crud_model->add_blog();
            $this->session->set_flashdata('flash_message', get_phrase('blog_added_successfully'));
            redirect(site_url('admin/blog'), 'refresh');
        } elseif ($param1 == 'update') {
            $this->crud_model->update_blog($param2);
            $this->session->set_flashdata('flash_message', get_phrase('blog_updated_successfully'));
            redirect(site_url('admin/blog'), 'refresh');
        } elseif ($param1 == 'status') {
            $this->crud_model->update_blog_status($param2);
            $this->session->set_flashdata('flash_message', get_phrase('blog_status_has_been_updated'));
            redirect(site_url('admin/blog'), 'refresh');
        } elseif ($param1 == 'delete') {
            $this->crud_model->blog_delete($param2);
            $this->session->set_flashdata('flash_message', get_phrase('blog_deleted_successfully'));
            redirect(site_url('admin/blog'), 'refresh');
        }
        $page_data['blogs']      = $this->crud_model->get_blogs();
        $page_data['page_title'] = get_phrase('blog');
        $page_data['page_name']  = 'blog';
        $this->load->view('backend/index', $page_data);
    }

    public function instructors_pending_blog($param1 = "", $param2 = "")
    {
        if ($param1 == 'approval_request') {
            $this->crud_model->approve_blog($param2);
            $this->session->set_flashdata('flash_message', get_phrase('the_blog_has_been_approved'));
            redirect(site_url('admin/instructors_pending_blog'), 'refresh');
        } elseif ($param1 == 'delete') {
            $this->crud_model->blog_delete($param2);
            $this->session->set_flashdata('flash_message', get_phrase('blog_deleted_successfully'));
            redirect(site_url('admin/instructors_pending_blog'), 'refresh');
        }
        $page_data['pending_blogs'] = $this->crud_model->get_instructors_pending_blog();
        $page_data['page_title']    = get_phrase('instructors_pending_blog');
        $page_data['page_name']     = 'instructors_pending_blog';
        $this->load->view('backend/index', $page_data);
    }

    public function blog_settings($param1 = "")
    {
        // بوابة الصلاحية — كانت غائبة
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'));
        }
        check_permission('blog');

        if ($param1 == 'update') {
            $this->crud_model->update_blog_settings();
            $this->session->set_flashdata('flash_message', get_phrase('blog_settings_updated_successfully'));
            redirect(site_url('admin/blog_settings'), 'refresh');
        }
        $page_data['page_title'] = get_phrase('blog_settings');
        $page_data['page_name']  = 'blog_settings';
        $this->load->view('backend/index', $page_data);
    }
    //End blog

    //Don't remove this code for security reasons





    //Start Academy Cloud coding
    //End of Academy Cloud coding

    //Start data center
    //End of data center

    //Select 2 server-side user data
    public function get_select2_user_data($default = "")
    {
        $response = [];
        $result   = $this->db->where('role_id !=', 1)->group_start()->like('first_name', $_GET['searchVal'])->or_like('last_name', $_GET['searchVal'])->or_like('email', $_GET['searchVal'])->group_end()->limit(100)->get('users')->result_array();
        if ($default != '') {
            $response[] = [['id' => $default, 'text' => get_phrase($default)]];
        }
        foreach ($result as $key => $row) {
            $response[] = ['id' => $row['id'], 'text' => $row['first_name'] . ' ' . $row['last_name'] . '(' . $row['email'] . ')'];
        }
        echo json_encode($response);
    }
    //Select 2 server-side user data
    public function get_select2_instructor_data($default = "")
    {
        $response = [];
        $result   = $this->db->where('is_instructor', 1)->group_start()->like('first_name', $_GET['searchVal'])->or_like('last_name', $_GET['searchVal'])->or_like('email', $_GET['searchVal'])->group_end()->limit(100)->get('users')->result_array();
        if ($default != '') {
            $response[] = [['id' => $default, 'text' => get_phrase($default)]];
        }
        foreach ($result as $key => $row) {
            $response[] = ['id' => $row['id'], 'text' => $row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['email'] . ')'];
        }
        echo json_encode($response);
    }

    //Select 2 server-side enrollable data
    public function get_select2_course_for_enroll($default = "")
    {
        $response = [];
        $result   = $this->db->group_start()->where('status', 'active')->or_where('status', 'private')->group_end()->group_start()->like('title', $_GET['searchVal'])->or_like('description', $_GET['searchVal'])->group_end()->limit(100)->get('course')->result_array();
        if ($default != '') {
            $response[] = [['id' => $default, 'text' => get_phrase($default)]];
        }
        foreach ($result as $key => $row) {
            $user       = $this->user_model->get_all_user($row['creator'])->row_array();
            $response[] = ['id' => $row['id'], 'text' => $row['title'] . ' (' . get_phrase('Creator') . ': ' . $user['first_name'] . ' ' . $user['last_name'] . ')'];
        }
        echo json_encode($response);
    }

    //Select 2 server-side general data
    public function get_select2_general_course($default = "")
    {
        $response = [];
        $result   = $this->db->where('course_type', 'general')->group_start()->like('title', $_GET['searchVal'])->or_like('description', $_GET['searchVal'])->group_end()->limit(100)->get('course')->result_array();
        if ($default != '') {
            $response[] = [['id' => $default, 'text' => get_phrase($default)]];
        }
        foreach ($result as $key => $row) {
            $user       = $this->user_model->get_all_user($row['creator'])->row_array();
            $response[] = ['id' => $row['id'], 'text' => $row['title'] . ' (' . get_phrase('Creator') . ': ' . $user['first_name'] . ' ' . $user['last_name'] . ')'];
        }
        echo json_encode($response);
    }


    // هل إضافة الذكاء الاصطناعي (نموذج ai_model) مثبتة فعلا؟

    // شاشة كاملة مفهومة تبين أن الإضافة غير مثبتة بدل الخطأ القاتل






    public function delete_course_review($rating_id = "")
    {
        $query          = $this->db->where('id', $rating_id);
        $course_details = $this->db->where('id', $query->get('rating')->row('ratable_id'))->get('course')->row_array();
        $this->db->where('id', $rating_id)->delete('rating');

        $this->session->set_flashdata('flash_message', get_phrase('user_review_deleted_successfully'));
        redirect(site_url('home/course/' . slugify($course_details['title']) . '/' . $course_details['id']), 'refresh');
    }

    //Start Notification
    public function get_my_notification($type = "")
    {
        $user_id = $this->session->userdata('user_id');

        if ($type == 'mark_all_as_read') {
            $this->db->where('to_user', $user_id);
            $this->db->update('notifications', ['status' => 1]);
        }

        if ($type == 'remove_all') {
            $this->db->where('to_user', $user_id);
            $this->db->delete('notifications');
        }

        $this->db->where('to_user', $user_id);
        $this->db->limit(50);
        $query                      = $this->db->order_by('status ASC, id desc');
        $page_data['notifications'] = $query->get('notifications');

        if ($query->where('status', 0)->get('notifications')->num_rows() > 0):
            $response['notification_icon_class'] = 'noti-icon-badge';
        else:
            $response['notification_icon_class'] = '';
        endif;
        $response['rendered_view'] = $this->load->view('backend/header_notification', $page_data, true);

        echo json_encode($response);
    }
    //End notification

    public function language_import()
    {
        $this->load->dbforge();

        foreach ($_FILES['language_files']['name'] as $key => $language) {
            $language_name = strtolower(preg_replace('/\s+/', '_', explode('.', $_FILES['language_files']['name'][$key])[0]));
            //Create language column if not exist
            if (! $this->db->field_exists($language_name, 'language')) {
                $fields = [
                    $language_name => [
                        'type'      => 'LONGTEXT',
                        'default'   => null,
                        'null'      => true,
                        'collation' => 'utf8_unicode_ci',
                    ],
                ];
                $this->dbforge->add_column('language', $fields);
            }

            $language_content_arr = json_decode(file_get_contents($_FILES['language_files']['tmp_name'][$key]), true);
            if (is_array($language_content_arr)) {
                //Upload the json file
                move_uploaded_file($_FILES['language_files']['tmp_name'][$key], 'application/language/' . $language_name . '.json');
            } else {
                $this->session->set_flashdata('error_message', get_phrase('JSON_validation_failed') . '!');
                redirect(site_url('admin/manage_language'), 'refresh');
            }

            foreach ($language_content_arr as $phrase_key => $phrase) {
                $phrase_key = strtolower(preg_replace('/\s+/', '_', $phrase_key));
                $query      = $this->db->get_where('language', ['phrase' => $phrase_key]);

                if ($query->num_rows() > 0) {
                    $this->db->where('phrase', $phrase_key);
                    $this->db->update('language', [$language_name => $phrase]);
                } else {
                    $this->db->insert('language', ['phrase' => $phrase_key, $language_name => $phrase]);
                }
            }
        }

        $this->session->set_flashdata('flash_message', get_phrase('language_file_import_successfully'));
        redirect(site_url('admin/manage_language'), 'refresh');
    }

    public function export_language($language)
    {
        $this->load->helper('download');
        $language     = strtolower($language);
        $json_content = [];

        foreach ($this->db->get('language')->result_array() as $row) {
            $json_content[$row['phrase']] = $row[$language];
        }
        force_download($language . '.json', json_encode($json_content));
    }

    /**
     * مشتركو النشرة البريدية.
     *
     * TQ-DT-GONE — كانت الصفحة جدول DataTables يجلب صفوفه بـPOST إلى
     * المسار نفسه، والمتحكم يرد HTML مبنيا بالسلاسل (`<div class="dropright">`
     * وقائمة منسدلة و`<script>$("a,i").tooltip()</script>` **داخل كل خلية**
     * — أي سكربت يحقن مرة لكل صف). وأثر ذلك ثلاثة:
     *
     * ١ — أي تعثر يخرج نافذة `alert()` بيضاء بالإنجليزية فوق اللوحة.
     * ٢ — العرض يكتب في المتحكم لا في القالب، فلا سبيل إلى تغيير مظهر
     *     صف إلا بتحرير PHP.
     * ٣ — `$filtered_number_of_row = count(...)` بعد `limit` — أي أن عدد
     *     نتائج البحث كان **عدد صفحة واحدة** أبدا، فالترقيم يقول
     *     «١٠ من ١٠» مهما كان في القاعدة.
     *
     * صارت عرضا من الخادم بترقيم صحيح وبحث في الرابط.
     */
    public function subscribed_user($type = "", $id = "")
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }
        check_permission('newsletter');

        if ($type === 'delete') {
            $this->db->where('id', (int) $id)->delete('newsletter_subscriber');
            $this->session->set_flashdata('flash_message', get_phrase('Newsletter subscription deleted successfully'));
            redirect(site_url('admin/subscribed_user'), 'refresh');
        }

        $per   = 25;
        $page  = max(1, (int) $this->input->get('page'));
        $q     = trim((string) $this->input->get('q', true));

        if ($q !== '') $this->db->like('email', $q);
        $total = (int) $this->db->count_all_results('newsletter_subscriber');

        $pages = max(1, (int) ceil($total / $per));
        $page  = min($page, $pages);

        if ($q !== '') $this->db->like('email', $q);
        $rows = $this->db->order_by('id', 'DESC')
                         ->limit($per, ($page - 1) * $per)
                         ->get('newsletter_subscriber')->result_array();

        /* المشترك قد يكون مسجلا في المنصة وقد لا يكون. والربط كان
           استعلاما لكل صف؛ صار استعلاما واحدا لكل الصفحة. */
        $emails = array_column($rows, 'email');
        $known  = array();
        if ($emails) {
            $users = $this->db->select('email, first_name, last_name, is_instructor')
                              ->where_in('email', $emails)
                              ->get('users')->result_array();
            foreach ($users as $u) $known[strtolower($u['email'])] = $u;
        }

        $page_data['rows']       = $rows;
        $page_data['known']      = $known;
        $page_data['total']      = $total;
        $page_data['page_no']    = $page;
        $page_data['page_count'] = $pages;
        $page_data['per_page']   = $per;
        $page_data['search']     = $q;

        $page_data['page_name']  = 'subscribed_user';
        $page_data['page_title'] = get_phrase('Subscribed user');
        $this->load->view('backend/index', $page_data);
    }


    /**
     * نماذج النشرة — صفحات كاملة لا نوافذ.
     *
     * كانت الثلاثة تحمل قالبها **عاريا** (`load->view('backend/admin/…')`
     * بلا غلاف)، وتفتح في نافذة `showAjaxModal`. وثلاثة أعطال في ذلك:
     *
     * ١ — **الرابط المباشر يخرج صفحة بلا هيكل.** من فتح
     *     `admin/newsletter_edit_form/3` في تبويب جديد يرى نموذجا عاريا
     *     على خلفية بيضاء بلا شريط ولا ترويسة ولا ورقة أنماط.
     * ٢ — **لا بوابة صلاحية ولا بوابة دخول.** الثلاثة بلا
     *     `admin_login` ولا `check_permission` — أي أن أي زائر يقرأ
     *     نموذج الإرسال ويرى قائمة المستخدمين كاملة.
     * ٣ — **المحرر لا يعمل داخل النافذة.** `initSummerNote` تنادى في
     *     السطر عند الحقن، وقد تسبق وصول العنصر إلى الشجرة.
     *
     * فصارت صفحات كاملة تمر بالغلاف، ومحروسة.
     */
    public function newsletter_add_form()
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }
        check_permission('newsletter');

        $page_data['page_name']  = 'add_newsletter';
        $page_data['page_title'] = get_phrase('Newsletter template');
        $this->load->view('backend/index', $page_data);
    }

    public function newsletter_edit_form($id)
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }
        check_permission('newsletter');

        $page_data['newsletter'] = $this->db->where('id', (int) $id)->get('newsletters')->row_array();
        $page_data['page_name']  = 'edit_newsletter';
        $page_data['page_title'] = get_phrase('Edit newsletter template');
        $this->load->view('backend/index', $page_data);
    }

    public function newsletter_send_form($id)
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }
        check_permission('newsletter');

        $page_data['newsletter'] = $this->db->where('id', (int) $id)->get('newsletters')->row_array();
        $page_data['page_name']  = 'send_newsletter';
        $page_data['page_title'] = get_phrase('Send Newsletter');
        $this->load->view('backend/index', $page_data);
    }

    public function newsletters($type = "", $id = "")
    {
        if ($type == 'add') {
            $this->crud_model->add_newsletter();
            $this->session->set_flashdata('flash_message', get_phrase('Newsletter added successfully'));
            redirect(site_url('admin/newsletters'), 'refresh');
        }
        if ($type == 'edit') {
            $this->crud_model->update_newsletter($id);
            $this->session->set_flashdata('flash_message', get_phrase('Newsletter updated successfully'));
            redirect(site_url('admin/newsletters?tab=' . $id), 'refresh');
        }

        if ($type == 'send') {
            $to = [];

            $subject     = $this->input->post('subject');
            $description = $this->input->post('description', false);
            $send_to     = $this->input->post('send_to');

            if ($send_to == 'all') {
                $all_users = $this->db->where('status', 1)->where('role_id !=', 1)->get('users')->result_array();
                foreach ($all_users as $key => $all_user):
                    $to[] = $all_user['email'];
                endforeach;
            } elseif ($send_to == 'student') {
                $all_users = $this->db->where('status', 1)->where('role_id !=', 1)->where('is_instructor !=', 1)->get('users')->result_array();
                foreach ($all_users as $key => $all_user):
                    $to[] = $all_user['email'];
                endforeach;
            } elseif ($send_to == 'instructor') {
                $all_users = $this->db->where('status', 1)->where('role_id !=', 1)->where('is_instructor', 1)->get('users')->result_array();
                foreach ($all_users as $key => $all_user):
                    $to[] = $all_user['email'];
                endforeach;
            } elseif ($send_to == 'all_subscriber') {
                $all_subscriber = $this->db->get('newsletter_subscriber')->result_array();
                foreach ($all_subscriber as $key => $subscriber):
                    $to[] = $subscriber['email'];
                endforeach;
            } elseif ($send_to == 'registered_subscriber') {
                $all_subscriber = $this->db->get('newsletter_subscriber')->result_array();
                foreach ($all_subscriber as $key => $subscriber):
                    $registration = $this->db->where('status', 1)->where('email', $subscriber['email'])->get('users');
                    if ($registration->num_rows() > 0) {
                        $to[] = $subscriber['email'];
                    }
                endforeach;
            } elseif ($send_to == 'non_registered_subscriber') {
                $all_subscriber = $this->db->get('newsletter_subscriber')->result_array();
                foreach ($all_subscriber as $key => $subscriber):
                    $registration = $this->db->where('status', 1)->where('email', $subscriber['email'])->get('users');
                    if ($registration->num_rows() == 0) {
                        $to[] = $subscriber['email'];
                    }
                endforeach;
            } elseif ($send_to == 'selected_user') {
                $user_ids  = $this->input->post('user_id');
                $all_users = $this->db->where_in('id', $user_ids)->get('users')->result_array();
                foreach ($all_users as $key => $all_user):
                    $to[] = $all_user['email'];
                endforeach;
            } else {
                $this->session->set_flashdata('error_message', get_phrase('You must select at least one single user'));
                redirect(site_url('admin/newsletters'), 'refresh');
            }

            $email_data['subject'] = $subject;
            $email_data['message'] = $description;
            $email_template        = $this->load->view('email/static_common_template', $email_data, true);

            $this->crud_model->assignEmailToSendList($to, $subject, $email_template);

            //$this->email_model->send_smtp_mail($email_template, $subject, $to);//
            $this->session->set_flashdata('flash_message', get_phrase('Users are assigned to newsletter mailing list') . ' ' . get_phrase('Please wait'));
            redirect(site_url('admin/newsletters'), 'refresh');
        }

        if ($type == 'delete') {
            $this->crud_model->delete_newsletter($id);
            $this->session->set_flashdata('flash_message', get_phrase('Newsletter deleted successfully'));
            redirect(site_url('admin/newsletters'), 'refresh');
        }
        $page_data['page_name']  = 'newsletters';
        $page_data['page_title'] = get_phrase('Newsletters');
        $this->load->view('backend/index', $page_data);
    }

    /**
     * سجل إرسال النشرة — عرض من الخادم.
     *
     * TQ-DT-GONE — كان جدول DataTables يجلب صفوفه بـPOST، والمتحكم يبني
     * خلاياه HTML بالسلاسل. ومعه ثلاثة أعطال:
     *
     * ١ — **`$filtered_number_of_row = count($rows)` بعد `limit`** — أي
     *     أن عدد نتائج البحث هو عدد صفحة واحدة أبدا، فيقول الترقيم
     *     «١٠ من ١٠» ولو كان في القاعدة ألف.
     * ٢ — **شرط البحث يتجاهل الحالة.** `like(email) or_like(subject)
     *     or_like(description)` ثم `group_start()->where(status)` — و
     *     `OR` تسبق المجموعة، فالاستعلام يقرأ
     *     `email LIKE … OR subject LIKE … OR (description LIKE … AND status = …)`.
     *     أي أن البحث في صفحة «المتعثرة» **كان يرد رسائل ناجحة**.
     * ٣ — **زر الإرسال بلا حراسة صلاحية** ولا توكن.
     *
     * و«faild» بخطئها الإملائي — الكتابة إليها في `Email_model` بالإملاء
     * نفسه، فتصحيح القراءة وحدها يجعل الصفحة فارغة أبدا.
     */
    public function newsletter_history($type = "", $id = "")
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }
        check_permission('newsletter');

        if ($type === 'send' && $id !== '') {
            $row = $this->db->where('id', (int) $id)->get('newsletter_histories')->row_array();

            if (!$row) {
                $this->session->set_flashdata('error_message', get_phrase('Failed to send mail'));
                redirect(site_url('admin/newsletter_history/pending'), 'refresh');
            }

            $sent = $this->email_model->send_smtp_mail($row['description'], $row['subject'], $row['email']);

            if ($sent) {
                $this->db->where('id', (int) $id)->update('newsletter_histories', ['status' => 'sent']);
                $this->session->set_flashdata('flash_message', get_phrase('Mail sent successfully'));
            } else {
                $this->session->set_flashdata('error_message', get_phrase('Failed to send mail'));
            }

            redirect(site_url('admin/newsletter_history/' . ($row['status'] ?: 'pending')), 'refresh');
        }

        $known = ['pending', 'sent', 'faild', 'unable'];
        if (!in_array($type, $known, true)) $type = 'pending';

        $per  = 30;
        $page = max(1, (int) $this->input->get('page'));
        $q    = trim((string) $this->input->get('q', true));

        /* الحالة شرط ثابت، والبحث مجموعة داخله — لا بجانبه. */
        $scope = function () use ($type, $q) {
            $this->db->where('status', $type);
            if ($q !== '') {
                $this->db->group_start()
                         ->like('email', $q)
                         ->or_like('subject', $q)
                         ->group_end();
            }
        };

        $scope();
        $total = (int) $this->db->count_all_results('newsletter_histories');

        $pages = max(1, (int) ceil($total / $per));
        $page  = min($page, $pages);

        $scope();
        $page_data['rows'] = $this->db->order_by('id', 'DESC')
                                      ->limit($per, ($page - 1) * $per)
                                      ->get('newsletter_histories')->result_array();

        $page_data['type']       = $type;
        $page_data['total']      = $total;
        $page_data['page_no']    = $page;
        $page_data['page_count'] = $pages;
        $page_data['per_page']   = $per;
        $page_data['search']     = $q;

        $page_data['page_name']  = 'newsletter_history';
        $page_data['page_title'] = get_phrase('Newsletter history');
        $this->load->view('backend/index', $page_data);
    }
    public function newsletter_statistics()
    {
        echo $this->load->view('backend/admin/newsletter_statistics', [], true);
    }

    public function student_academic_progress($course_id = "")
    {
        $course_details    = $this->crud_model->get_course_by_id($course_id)->row_array();
        $multi_instructors = explode(',', $course_details['user_id']);

        $page_data['course_details'] = $course_details;
        $this->load->view('backend/admin/student_academic_progress', $page_data);
    }

    public function student_academic_quiz_result($course_id = "", $student_id = "")
    {
        $course_details    = $this->crud_model->get_course_by_id($course_id)->row_array();
        $multi_instructors = explode(',', $course_details['user_id']);

        if (! in_array($this->session->userdata('user_id'), $multi_instructors)) {
            return false;
        }

        $page_data['course_details'] = $course_details;
        $page_data['student_id']     = $student_id;
        $this->load->view('backend/admin/student_academic_quiz_result', $page_data);
    }


    public function student_certificate($user_id = "", $course_id = "")
    {
        // إضافة الشهادات غير مثبتة -> رسالة واضحة بدل «Unable to load model»
        if (! file_exists(APPPATH . 'models/addons/Certificate_model.php')
            && ! file_exists(APPPATH . 'models/addons/certificate_model.php')) {
            $this->session->set_flashdata('error_message', 'إضافة الشهادات غير مثبتة على النظام.');
            if (! empty($course_id)) {
                redirect(site_url('admin/course_form/course_edit/' . $course_id . '?tab=academic_progress'));
            } else {
                redirect(site_url('admin/courses'), 'refresh');
            }
            return;
        }
        $this->load->model('addons/Certificate_model', 'certificate_model');
        $course_progress = $this->crud_model->get_watch_histories($user_id, $course_id)->row('course_progress');
        if ($course_progress >= 100) {
            $this->certificate_model->check_certificate_eligibility($course_id, $user_id);
            $certificate = $this->db->get_where('certificates', ['course_id' => $course_id, 'student_id' => $user_id]);
            redirect(site_url('certificate/' . $certificate->row('shareable_url')));
        } else {
            $this->session->set_flashdata('error_message', get_phrase('The course is not compleated yet'));
            redirect(site_url('admin/course_form/course_edit/' . $course_id . '?tab=academic_progress'));
        }
    }

    public function contact($type = "", $id = "")
    {
        /**
         * حذف جماعي.
         *
         * كان يقرأ `?selected_ids=1,2,3` من الرابط — أي أن **رابطا واحدا
         * يمسح صفوفا بمجرد جلبه**، من زاحف أو من استباق تحميل، ويترك
         * قائمة المعرفات مكتوبة في سجل الخادم. صار يقرأ مصفوفة
         * `ids[]` من جسم POST، ولا شيء يحذف إلا بإرسال نموذج بتوكن.
         *
         * و`explode(',', '')` ترد `['']` لا مصفوفة فارغة، فشرط
         * `!empty($ids)` كان صادقا أبدا — والحذف ينفذ بـ`WHERE id IN ('')`،
         * فيقال «حذفت الرسائل» ولم يحذف شيء.
         */
        if ($type == 'delete_selected_contact') {
            $ids = array_filter(array_map('intval', (array) $this->input->post('ids')));

            if ($ids) {
                $this->db->where_in('id', $ids)->delete('contact');
                $this->session->set_flashdata('flash_message', get_phrase('Contacts deleted successfully'));
            } else {
                $this->session->set_flashdata('error_message', get_phrase('No contacts selected for deletion'));
            }

            redirect(site_url('admin/contact'), 'refresh');
        }

        if ($type == 'delete') {
            $this->db->where('id', $id)->delete('contact');
            $this->session->set_flashdata('flash_message', get_phrase('Contact deleted successfully'));
            redirect(site_url('admin/contact'), 'refresh');
        }

        if ($type == '') {
            /**
             * TQ-DT-GONE — كانت هذه الصفحة جدول DataTables يجلب صفوفه
             * من `admin/contact/data-table`، والمتحكم يبني خلاياه HTML
             * بالسلاسل: قائمة `dropright` منسدلة، و`<script>$("a,i").tooltip()</script>`
             * **داخل كل خلية إجراءات** — أي سكربت يحقن ويشغل مرة لكل صف.
             *
             * وثلاثة أعطال تسقط معها:
             *
             * ١ — «تحديد الكل» كان يحدد **صفحة واحدة**، ومصفوفة
             *     `selectedRows` تفرغ كاملة عند إلغاء تحديد واحد
             *     (`selectedRows = []` في فرع `else`) — فمن حدد عشرة
             *     وألغى واحدا فقد التسعة.
             * ٢ — الحذف الجماعي يمر بـ`GET ?selected_ids=1,2,3`: رابط
             *     يحذف بمجرد جلبه، وقائمة معرفات في سجل الخادم.
             * ٣ — `has_read` كانت تحدث في نداء الـAJAX، فالشارة في
             *     الشريط الجانبي لا تصفر إلا بعد أن يرسم الجدول.
             *
             * والرسائل هنا نصوص طويلة لا صفوف قصيرة، فتعرض بطاقات لا
             * جدولا: الجدول كان يقص الرسالة أو يمدد الصف عشرة أسطر.
             */
            $this->db->where('has_read', null)->update('contact', ['has_read' => 1]);

            $per  = 20;
            $page = max(1, (int) $this->input->get('page'));
            $q    = trim((string) $this->input->get('q', true));

            $scope = function () use ($q) {
                if ($q !== '') {
                    $this->db->group_start()
                             ->like('first_name', $q)
                             ->or_like('last_name', $q)
                             ->or_like('email', $q)
                             ->or_like('phone', $q)
                             ->or_like('message', $q)
                             ->group_end();
                }
            };

            $scope();
            $total = (int) $this->db->count_all_results('contact');

            $pages = max(1, (int) ceil($total / $per));
            $page  = min($page, $pages);

            $scope();
            $rows = $this->db->order_by('id', 'DESC')
                             ->limit($per, ($page - 1) * $per)
                             ->get('contact')->result_array();

            /* حالة المرسل في المنصة — استعلام واحد لا واحد لكل رسالة. */
            $known = [];
            $mails = array_filter(array_column($rows, 'email'));
            if ($mails) {
                foreach ($this->db->select('email, is_instructor')->where_in('email', $mails)
                                  ->get('users')->result_array() as $u) {
                    $known[strtolower($u['email'])] = (int) $u['is_instructor'] === 1 ? 'teacher' : 'student';
                }
            }

            $page_data['rows']       = $rows;
            $page_data['known']      = $known;
            $page_data['total']      = $total;
            $page_data['page_no']    = $page;
            $page_data['page_count'] = $pages;
            $page_data['per_page']   = $per;
            $page_data['search']     = $q;

            $page_data['page_name']  = 'contact';
            $page_data['page_title'] = get_phrase('Contact');
            $this->load->view('backend/index', $page_data);
        }

        if ($type == 'send_reply' && $id != '') {
            $message         = $this->input->post('reply_message');
            $contact_details = $this->crud_model->get_contacts($id)->row_array();
            $this->email_model->send_smtp_mail($message, get_phrase('Reply from - ') . get_settings('system_name'), $contact_details['email']);
            $this->db->where('id', $id)->update('contact', ['replied' => 1]);
            $this->session->set_flashdata('flash_message', get_phrase('Reply sent successfully'));
            redirect(site_url('admin/contact'), 'refresh');
        }
    }

    public function update_language_direction()
    {
        $language      = $this->input->post('language');
        $dir           = $this->input->post('dir');
        $language_dirs = get_settings('language_dirs') ? json_decode(get_settings('language_dirs'), true) : ['english' => 'ltr'];

        $language_dirs[$language] = $dir;

        $data['value'] = json_encode($language_dirs);

        if ($this->db->get_where('settings', ['key' => 'language_dirs'])->num_rows() > 0) {
            $this->db->where('key', 'language_dirs')->update('settings', $data);
        } else {
            $data['key'] = 'language_dirs';
            $this->db->insert('settings', $data);
        }
        echo get_phrase('Language direction updated successfully');
    }


    public function cronjob($type = "")
    {
        // Write some content to the cron file for CURL call.
        $content = '<?php
        $url = "' . base_url("home/sendEmailToAssignedAddresses") . '";
        $ch = curl_init($url);

        // Set cURL options
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Execute cURL session
        $response = curl_exec($ch);

        // Close cURL session
        curl_close($ch);';

        //White this file using curl ($content) content
        $newsletter_cron_file_path = "uploads/cronjob/newsletter_cron.php";
        if (file_exists($newsletter_cron_file_path)) {
            unlink($newsletter_cron_file_path);
        }

        // //CRON CONTENTS
        // // Get PHP Binary Path
        // $setInterval_1m = '* * * * *'; // for every 1 minute
        // //$phpIniFile = php_ini_loaded_file();
        // $phpBinaryPath = PHP_BINDIR . DIRECTORY_SEPARATOR . 'php';
        // // Get the application path
        // $applicationPath = realpath(APPPATH . '..') . '/' . $newsletter_cron_file_path;

        // Get PHP Binary Path
        $phpSapi       = php_sapi_name();
        $phpBinaryPath = ($phpSapi === 'cli') ? PHP_BINARY : PHP_BINDIR . DIRECTORY_SEPARATOR . 'php';
                                        // Rest of your code remains unchanged
        $setInterval_1m  = '* * * * *'; // for every 1 minute
        $applicationPath = realpath(APPPATH . '..') . '/' . $newsletter_cron_file_path;

        if ($type == 'start') {
            if (! is_dir('uploads/cronjob')) {
                mkdir('uploads/cronjob', 0777, true);
            }

            // Open the file for writing (creates the file if it doesn't exist)
            $fileHandle = fopen($newsletter_cron_file_path, "w");
            // Check if the file was opened successfully
            if ($fileHandle) {
                fwrite($fileHandle, $content);
                // Close the file handle
                fclose($fileHandle);
            } else {
                $this->session->set_flashdata('error_message', get_phrase("Failed to create the cron file") . '. File path:' . $newsletter_cron_file_path);
                redirect(site_url('admin/newsletters'), 'refresh');
            }

            // Execute Shell Command
            // $cronCommand = 'crontab -l | { cat; echo "' . $setInterval_1m . ' ' . $phpBinaryPath . ' ' . $applicationPath . '"; } | crontab -';
            // exec($cronCommand, $output, $return_var);

            // if ($return_var !== 0) {
            //     unlink($newsletter_cron_file_path);
            //     $this->session->set_flashdata('error_message', get_phrase('Cron job setup failed') . ' Output:' . implode("\n", $output));
            //     redirect(site_url('admin/newsletters'), 'refresh');
            // } else {
            //     $this->session->set_flashdata('flash_message', get_phrase('Cron job successfully set up'));
            //     redirect(site_url('admin/newsletters'), 'refresh');
            // }

            redirect(site_url('admin/newsletters'), 'refresh');
        } elseif ($type == 'stop') {
            // Remove Cron Job
            // $cronCommandRemove = 'crontab -l | grep -v "' . $phpBinaryPath . ' ' . $applicationPath . '" | crontab -';
            // exec($cronCommandRemove, $outputRemove, $returnVarRemove);

            // if ($returnVarRemove !== 0) {
            //     $this->session->set_flashdata('error_message', get_phrase('Cron job removal failed') . ' Output:' . implode("\n", $outputRemove));
            //     redirect(site_url('admin/newsletters'), 'refresh');
            // } else {
            //     $this->session->set_flashdata('flash_message', get_phrase('Cron job successfully removed'));
            //     redirect(site_url('admin/newsletters'), 'refresh');
            // }

            $newsletter_cron_file_path = "uploads/cronjob/newsletter_cron.php";
            unlink($newsletter_cron_file_path);
            redirect(site_url('admin/newsletters'), 'refresh');
        }
    }





    public function change_course_author($course_id = "")
    {
        if (isset($_POST) && count($_POST) > 0) {
            if ($_POST['instructor_id'] > 0) {
                $this->db->where('id', $course_id)->update('course', ['creator' => $_POST['instructor_id']]);
                $this->session->set_flashdata('flash_message', get_phrase("Course author changed successfully"));
            } else {
                $this->session->set_flashdata('error_message', get_phrase("Something is wrong"));
            }
            redirect(site_url('admin/course_form/course_edit/' . $course_id . '?tab=basic'), 'refresh');
        } else {
            $page_data['instructors']    = $this->user_model->get_instructor()->result_array();
            $page_data['course_details'] = $this->crud_model->get_course_by_id($course_id)->row_array();
            $this->load->view('backend/admin/change_course_author', $page_data);
        }
    }

    public function seo_settings($param1 = "", $param2 = "")
    {
        if ($param1 == 'update') {
            $this->crud_model->save_seo_settings($param2);
            $this->session->set_flashdata('flash_message', get_phrase('seo_settings_updated_successfully'));
            redirect(site_url('admin/seo_settings/' . $param2), 'refresh');
        }
        $page_data['seo_meta_tags'] = $this->crud_model->get_seo_meta_tags()->result_array();
        $page_data['active_tab']    = ! empty($param1) ? $param1 : 'home';
        $page_data['page_title']    = get_phrase('seo_settings');
        $page_data['page_name']     = 'seo_settings';
        $this->load->view('backend/index', $page_data);
    }

    public function sitemap_settings()
    {
        $blogs           = $this->crud_model->get_all_blogs()->result_array();
        $blog_categories = $this->crud_model->get_blog_categories()->result_array();
        $courses         = $this->crud_model->get_courses()->result_array();
        $categories      = $this->crud_model->get_categories()->result_array();

        // Construct URLs for each blog
        $blog_url_array = [];

        foreach ($blogs as $blog) {
            $slug             = slugify($blog['title']);
            $blog_id          = $blog['blog_id'];
            $url              = base_url("blog/details/$slug/$blog_id");
            $blog_url_array[] = $url;
        }

        // Construct URLs for each blog category
        $blog_category_url_array = [];

        foreach ($blog_categories as $blog_category) {
            $slug                      = $blog_category['slug'];
            $url                       = base_url("blogs?category=$slug");
            $blog_category_url_array[] = $url;
        }

        // Construct URLs for each category
        $category_url_array = [];

        foreach ($categories as $category) {
            $slug                 = $category['slug'];
            $url                  = base_url("home/courses?category=$slug");
            $category_url_array[] = $url;

            // Retrieve subcategories for the current category using its ID
            $sub_categories = $this->crud_model->get_sub_categories($category['id']);

            foreach ($sub_categories as $sub_category) {
                $sub_slug             = $sub_category['slug'];
                $sub_url              = base_url("home/courses?category=$sub_slug");
                $category_url_array[] = $sub_url;
            }
        }

        // Construct URLs for each course
        $course_url_array = [];

        foreach ($courses as $course) {
            $slug               = slugify($course['title']);
            $course_id          = $course['id'];
            $url                = base_url("home/course/$slug/$course_id");
            $course_url_array[] = $url;
        }

        $page_data['sitemap'] = [
            'key'   => 'sitemap_xml',
            'value' => get_settings('sitemap_xml'), // Fetch the sitemap XML content.
        ];
        $page_data['courses']         = $course_url_array;
        $page_data['categories']      = $category_url_array;
        $page_data['blogs']           = $blog_url_array;
        $page_data['blog_categories'] = $blog_category_url_array;
        $page_data['page_title']      = get_phrase('sitemap_settings');
        $page_data['page_name']       = 'sitemap_settings';
        $this->load->view('backend/index', $page_data);
    }

    public function export_enrol_history_csv()
    {
        // Check if the request method is POST
        if ($this->input->method() === 'post') {
            // Get enrol IDs from the request
            $enrol_ids = $this->input->post('enrol_ids');

            // Validate enrol IDs
            if (! is_array($enrol_ids) || empty($enrol_ids)) {
                show_error('No enrol IDs provided.', 400);
            }

            // Fetch enrol data for the provided IDs
            $this->db->where_in('id', $enrol_ids);
            $query = $this->db->get('enrol');

            // Check if data exists
            if ($query->num_rows() === 0) {
                show_error('No data found for the provided enrol IDs.', 404);
            }

            // Prepare the CSV header
            $csv_data = '"Id","Student Name","Course Title","Purchase Date","Expiry Date"' . "\n";

            // Populate the CSV rows
            foreach ($query->result_array() as $row) {
                // Fetch related user and course data
                $user_data   = $this->db->get_where('users', ['id' => $row['user_id']])->row_array();
                $course_data = $this->db->get_where('course', ['id' => $row['course_id']])->row_array();

                // Add a CSV row
                $csv_data .= '"' . $row['id'] . '",';
                $csv_data .= '"' . $user_data['first_name'] . ' ' . $user_data['last_name'] . '",';
                $csv_data .= '"' . $course_data['title'] . '",';
                $csv_data .= '"' . date('d-m-Y', $row['date_added']) . '",';
                $csv_data .= '"' . ($row['expiry_date'] ? date('d-m-Y', $row['expiry_date']) : 'Lifetime access') . '"' . "\n";
            }

            // Send the CSV data as a response
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="enrol_history.csv"');
            echo $csv_data;
            exit;
        } else {
            show_error('Invalid request method.', 405);
        }
    }

    public function enrol_list($course_id = "")
    {
        $course_details              = $this->crud_model->get_course_by_id($course_id)->row_array();
        $multi_instructors           = explode(',', $course_details['user_id']);
        $page_data['course_details'] = $course_details;

        $page_data['enrol_history'] = $this->crud_model->enrol_history($course_id);

        $this->load->view('backend/admin/course_enrol_list', $page_data);
    }

    public function export_student_progress_csv($course_id)
    {
        // Get the enrolments for the given course
        $enrolments = $this->db->where('course_id', $course_id)->get('enrol')->result_array();

        // Initialize an array to hold the CSV data
        $csv_data = '"ID","Enrolment ID","Student","Date Enrolled","Last Seen","Completed On","Progress","Completed Lessons","Watched Duration"' . "\n";

        // Initialize incremental ID
        $incremental_id = 1;

        // Loop through each enrolment and fetch the relevant data
        foreach ($enrolments as $enrolment) {
            // Fetch student data
            $student = $this->user_model->get_all_user($enrolment['user_id'])->row_array();

            // Fetch watch history for the student
            $watch_history = $this->db->where('course_id', $course_id)->where('student_id', $enrolment['user_id'])->get('watch_histories')->row_array();

            // Handle completed lessons and course progress
            $completed_lesson_arr = isset($watch_history['completed_lesson']) ? json_decode($watch_history['completed_lesson'], true) : [];
            $completed_lesson     = is_array($completed_lesson_arr) ? count($completed_lesson_arr) : 0;
            $course_progress      = isset($watch_history['course_progress']) ? $watch_history['course_progress'] : 0;

            // Format dates in d-m-Y format
            $enrollment_date = date('d-m-Y', $enrolment['date_added']);
            $last_seen       = isset($watch_history['date_updated']) ? date('d-m-Y, H:i a', $watch_history['date_updated']) : 'Not started yet';
            $completed_date  = isset($watch_history['completed_date']) ? date('d-m-Y', $watch_history['completed_date']) : 'Not completed yet';

                                         // Get watched duration
            $total_watched_duration = 0; // seconds
            $watched_durations      = $this->db->get_where('watched_duration', ['watched_student_id' => $enrolment['user_id'], 'watched_course_id' => $course_id]);
            foreach ($watched_durations->result_array() as $watched_duration) {
                $total_watched_duration += count(json_decode($watched_duration['watched_counter'], true)) * 5;
            }
            $watched_duration = seconds_to_time_format($total_watched_duration);

            // Prepare the data for the CSV row with Incremental ID
            $csv_data .= '"' . $incremental_id . '",';
            $csv_data .= '"' . $enrolment['id'] . '",';
            $csv_data .= '"' . $student['first_name'] . ' ' . $student['last_name'] . '",';
            $csv_data .= '"' . $enrollment_date . '",';
            $csv_data .= '"' . $last_seen . '",';
            $csv_data .= '"' . $completed_date . '",';
            $csv_data .= '"' . $course_progress . '%",';
            $csv_data .= '"' . $completed_lesson . ' out of 10",';
            $csv_data .= '"' . $watched_duration . '"' . "\n";

            // Increment the ID for the next row
            $incremental_id++;
        }

        // Set the headers to trigger a file download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="student_progress.csv"');
        echo $csv_data;
        exit;
    }

    /**
     * تصدير المسؤولين إلى CSV.
     *
     * TQ-CSV-PARTIAL — كان يقبل POST وحده، ويقرأ `admin_ids` من جسم
     * الطلب. والذي يرسلها سكربت في [admins.php] يجمعها **من صفوف الجدول
     * المعروضة**، فالملف الناتج لا يحوي إلا ما ظهر على الشاشة — ومن ضغط
     * «تصدير» وهو يقرأ صفحة من عشرة يحصل على عشرة ويظن أنه حصل على الكل.
     * ثم يبنى الرد `Blob` في المتصفح، فأي تعثر في السكربت يترك الزر
     * صامتا (المعالج يكتب في الطرفية وحدها).
     *
     * فصار رابطا عاديا: الخادم يعرف قائمته كاملة، والمتصفح ينزلها كما
     * ينزل أي ملف. و`fputcsv` تكتب الاقتباس والهروب — والكتابة اليدوية
     * السابقة كانت تكسر عند أول اسم فيه علامة اقتباس.
     */
    public function export_admins_csv()
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }
        check_permission('admin');

        $admins = $this->user_model->get_admins()->result_array();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="admins.csv"');

        $out = fopen('php://output', 'w');

        /* علامة الترتيب: بدونها يقرأ Excel العربية على أنها لاتينية
           فيعرض «Ø§Ù„Ø¥Ø¯Ø§Ø±Ø©» مكان كل اسم. */
        fwrite($out, "ï»¿");

        fputcsv($out, array('المعرف', 'الاسم', 'البريد الإلكتروني', 'الهاتف'));
        foreach ($admins as $row) {
            fputcsv($out, array(
                $row['id'],
                trim($row['first_name'] . ' ' . $row['last_name']),
                $row['email'],
                $row['phone'],
            ));
        }

        fclose($out);
        exit;
    }

    // Admin Create Review
    /**
     * نموذجا الرأي — صفحتان كاملتان لا نافذتان.
     * كانا يحملان قالبهما عاريا وبلا بوابة دخول ولا صلاحية.
     */
    public function review_add()
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }
        check_permission('settings');

        $page_data['page_name']  = 'review_add';
        $page_data['page_title'] = get_phrase('add_a_review');
        $this->load->view('backend/index', $page_data);
    }

    public function review_edit($id = '')
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }
        check_permission('settings');

        $page_data['rating']     = $this->db->get_where('rating', ['id' => (int) $id])->row_array();
        $page_data['page_name']  = 'review_edit';
        $page_data['page_title'] = get_phrase('update_review');
        $this->load->view('backend/index', $page_data);
    }
    public function review($param1 = "", $param2 = "")
    {
        if ($param1 == 'delete' && ! empty($param2)) {
            $this->crud_model->review_delete($param2);
            $this->session->set_flashdata('flash_message', get_phrase('review_deleted_successfully'));
            redirect(site_url('admin/frontend_settings?tab=review'), 'refresh');
        }
    }

    // Badges



    // Endbadges



// Custom Field Start

    /**
     * حراسة مسارات الأقسام المخصصة.
     *
     * كانت الخمسة **بلا فحص دخول ولا صلاحية**: `custom_field_add`
     * و`custom_field_section_update` و`custom_field_section_delete`
     * و`custom_field_item_update` و`custom_field_item_delete` — أي أن
     * أي زائر يكتب في صفحة أي كورس، ويرفع ملفا إلى `uploads/`، ويحذف
     * ما فيها.
     */
    private function tq_guard_custom_field()
    {
        if ($this->input->method(true) !== 'POST') show_404();
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }
        check_permission('course');
    }


public function custom_field_add($param2)
{
    $this->tq_guard_custom_field();
    $custom_type = $this->input->post('custom_type');
    if (!$custom_type) return;

    $course_id    = $param2;
    $custom_title = $this->input->post($custom_type . '_custom_title');

    // ================= CHECK EXISTING ROW =================
    $existing = $this->db->get_where('custom_fields', [
        'course_id'    => $course_id,
        'custom_type'  => $custom_type,
        'custom_title' => $custom_title
    ])->row_array();

    $existing_items = [];
    $counter = 1;

    if ($existing) {
        $decoded = json_decode($existing['custom_field'], true);
        $existing_items = $decoded['data'] ?? [];

        if (!empty($existing_items)) {
            $ids = array_column($existing_items, 'id');
            $counter = max($ids) + 1; 
        }
    }

    $custom_items = [];

    // ================= IMAGE =================
    if ($custom_type == 'image') {

        if (!file_exists('uploads/custom_fields')) {
            mkdir('uploads/custom_fields', 0777, true);
        }

        foreach ($this->input->post('image_title') as $k => $title) {

            $file = '';
            if (!empty($_FILES['image_file']['name'][$k])) {
                $ext  = pathinfo($_FILES['image_file']['name'][$k], PATHINFO_EXTENSION);
                $file = time() . '_' . md5(uniqid()) . '.' . $ext;
                move_uploaded_file(
                    $_FILES['image_file']['tmp_name'][$k],
                    'uploads/custom_fields/' . $file
                );
            }

            $custom_items[] = [
                'id'          => $counter++,
                'title'       => $title,
                'description' => $_POST['image_description'][$k] ?? '',
                'file'        => $file
            ];
        }
    }

    // ================= TEXT =================
    if ($custom_type == 'text') {
        foreach ($this->input->post('text_content') as $content) {
            $custom_items[] = [
                'id'          => $counter++,
                'title'       => '',
                'description' => $content,
                'file'        => ''
            ];
        }
    }

    // ================= SLIDER =================
    if ($custom_type == 'slider') {

        if (!file_exists('uploads/custom_fields')) {
            mkdir('uploads/custom_fields', 0777, true);
        }

        foreach ($this->input->post('slider_title') as $k => $title) {

            $files = [];

            if (!empty($_FILES['slider_images']['name'][$k])) {
                foreach ($_FILES['slider_images']['name'][$k] as $i => $img) {
                    if (!empty($img)) {
                        $ext  = pathinfo($img, PATHINFO_EXTENSION);
                        $name = time() . '_' . md5(uniqid()) . '.' . $ext;

                        move_uploaded_file(
                            $_FILES['slider_images']['tmp_name'][$k][$i],
                            'uploads/custom_fields/' . $name
                        );

                        $files[] = $name;
                    }
                }
            }

            $custom_items[] = [
                'id'          => $counter++,
                'title'       => $title,
                'description' => $_POST['slider_description'][$k] ?? '',
                'file'        => $files
            ];
        }
    }

    // ================= VIDEO =================
    if ($custom_type == 'video') {
        foreach ($this->input->post('video_url') as $url) {
            $custom_items[] = [
                'id'    => $counter++,
                'title' => '',
                'file'  => $url
            ];
        }
    }

    // ================= FAQ =================
    if ($custom_type == 'faq') {
        foreach ($this->input->post('faq_question') as $k => $q) {
            $custom_items[] = [
                'id'          => $counter++,
                'title'       => $q,
                'description' => $_POST['faq_answer'][$k] ?? '',
                'file'        => ''
            ];
        }
    }

    // ================= GALLERY =================
    if ($custom_type == 'gallery') {

        if (!file_exists('uploads/custom_fields')) {
            mkdir('uploads/custom_fields', 0777, true);
        }

        foreach ($_FILES['gallery_images']['name'] as $k => $img) {

            $file = '';
            if (!empty($img)) {
                $ext  = pathinfo($img, PATHINFO_EXTENSION);
                $file = time() . '_' . md5(uniqid()) . '.' . $ext;
                move_uploaded_file(
                    $_FILES['gallery_images']['tmp_name'][$k],
                    'uploads/custom_fields/' . $file
                );
            }

            $custom_items[] = [
                'id'          => $counter++,
                'file'        => $file
            ];
        }
    }

    // ================= SAVE =================
    if ($existing) {

        $all_items = array_merge($existing_items, $custom_items);

        $this->db->where('id', $existing['id']);
        $this->db->update('custom_fields', [
            'custom_field' => json_encode(['data' => $all_items], JSON_UNESCAPED_UNICODE),
            'updated_at'   => date('Y-m-d H:i:s')
        ]);

    } else {

        $this->db->insert('custom_fields', [
            'course_id'    => $course_id,
            'custom_type'  => $custom_type,
            'custom_title' => $custom_title,
            'custom_field' => json_encode(['data' => $custom_items], JSON_UNESCAPED_UNICODE),
            'sorting'      => 0,
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s')
        ]);

        $insert_id = $this->db->insert_id();
        $this->db->where('id', $insert_id)->update('custom_fields', ['sorting' => $insert_id]);
    }

    $this->session->set_flashdata('flash_message', get_phrase('custom_field_added_successfully'));
    redirect(site_url('admin/course_form/course_edit/' . $course_id));
}





public function custom_field_section_update($field_id)
{
    $this->tq_guard_custom_field();
    // Fetch existing row
    $field = $this->db->get_where('custom_fields', ['id' => $field_id])->row_array();
    if (!$field) {
        $this->session->set_flashdata('error_message', get_phrase('custom_field_not_found'));
        redirect(site_url('admin/courses'));
    }

    // Get new custom_title from form
    $custom_title = $this->input->post('custom_title');

    // Update only custom_title, keep custom_field JSON intact
    $this->db->where('id', $field_id);
    $this->db->update('custom_fields', [
        'custom_title' => $custom_title,
        'updated_at'   => date('Y-m-d H:i:s')
    ]);

    $this->session->set_flashdata('flash_message', get_phrase('custom_field_updated_successfully'));
    redirect(site_url('admin/course_form/course_edit/'.$field['course_id']));
}

public function custom_field_section_delete($id)
{
    $this->tq_guard_custom_field();
    $field = $this->db->get_where('custom_fields', ['id' => $id])->row_array();
    $this->db->where('id', $id);
    $this->db->delete('custom_fields');
    $this->session->set_flashdata('flash_message', get_phrase('custom_field_deleted_successfully'));
    redirect(site_url('admin/course_form/course_edit/' . $field['course_id']));
}

public function custom_field_item_update($field_id, $item_id)
{
    $this->tq_guard_custom_field();
    $field = $this->db->get_where('custom_fields', ['id' => $field_id])->row_array();
    if (!$field) {
        return;
    }

    $decoded = json_decode($field['custom_field'], true);
    $items   = $decoded['data'] ?? [];

    // loop 
    foreach ($items as &$item) {

        if ($item['id'] == $item_id) {

            // ===== TEXT DATA UPDATE =====
            if ($this->input->post('image_title')) {
                $item['title'] = $this->input->post('image_title')[0];
            }
            if ($this->input->post('image_description')) {
                $item['description'] = $this->input->post('image_description')[0];
            }
            if ($this->input->post('text_content')) {
                $item['description'] = $this->input->post('text_content')[0];
            }
            if ($this->input->post('video_url')) {
                $item['file'] = $this->input->post('video_url')[0];
            }
            if ($this->input->post('faq_question')) {
                $item['title'] = $this->input->post('faq_question')[0];
            }
            if ($this->input->post('faq_answer')) {
                $item['description'] = $this->input->post('faq_answer')[0];
            }

            // ===== IMAGE UPDATE (unlink old if new exists) =====
            if (!empty($_FILES['image_file']['name'][0])) {

                $old_file = $item['file'] ?? null;

                $file_name = time().'_'.$_FILES['image_file']['name'][0];
                $tmp_name  = $_FILES['image_file']['tmp_name'][0];

                move_uploaded_file(
                    $tmp_name,
                    'uploads/custom_fields/'.$file_name
                );

                // old image delete
                if ($old_file && file_exists('uploads/custom_fields/'.$old_file)) {
                    unlink('uploads/custom_fields/'.$old_file);
                }

                $item['file'] = $file_name;
            }

            break;
        }
    }

    // JSON update
    $decoded['data'] = $items;

    $this->db->where('id', $field_id)->update('custom_fields', [
        'custom_field' => json_encode($decoded),
        'updated_at'   => date('Y-m-d H:i:s')
    ]);

    $this->session->set_flashdata('flash_message', get_phrase('custom_field_item_updated_successfully'));
    redirect(site_url('admin/course_form/course_edit/' . $field['course_id']));
}



public function custom_field_item_delete($field_id = '', $item_id = '')
{
    $this->tq_guard_custom_field();
    $field = $this->db->get_where('custom_fields', ['id' => $field_id])->row_array();

    if (!$field) {
        $this->session->set_flashdata('error', 'Invalid field');
        redirect(site_url('admin/course_form'));
        return;
    }

    $custom_field = json_decode($field['custom_field'], true);

    if (!isset($custom_field['data']) || !is_array($custom_field['data'])) {
        $this->session->set_flashdata('success', 'Item deleted successfully');
        redirect(site_url('admin/course_form/course_edit/' . $field['course_id']));
        return;
    }

    $custom_field['data'] = array_values(array_filter(
        $custom_field['data'],
        function ($item) use ($item_id) {
            return isset($item['id']) && $item['id'] != $item_id;
        }
    ));

    $this->db->where('id', $field_id);
    $this->db->update('custom_fields', [
        'custom_field' => json_encode($custom_field)
    ]);

    $this->session->set_flashdata('success', 'Item deleted successfully');
    redirect(site_url('admin/course_form/course_edit/' . $field['course_id']));
}


public function custom_field_section_sort_update()
{
    $this->tq_guard_ajax_write();
    $order = $this->input->post('order');

    if (is_array($order) && count($order) > 0) {
        foreach ($order as $position => $id) {
            $this->db->where('id', $id);
            $this->db->update('custom_fields', [
                'sorting' => $position + 1
            ]);
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
}




// Custom Field End





    // Home page builder




                    public function encode_some_special_characters($content = "")
                    {
                    if ($content == "") {
                    return;
                    }

                    // $content = preg_replace('/&/', '&amp;', $content);
                    // $content = preg_replace('/"/', '&quot;', $content);
                    // $content = preg_replace('/</', '&lt;' , $content); // $content=preg_replace(' />/', '&gt;', $content);
                    // return $content;

                    $content = str_replace("&", "__apmsign_amp__", $content);
                    $content = str_replace('+', "__plussign_plus__", $content);

                    return $content;
                    }
                    public function decode_some_special_characters($content = "")
                    {
                    if ($content == "") {
                    return;
                    }

                    // $content = preg_replace('/&/', '&amp;', $content);
                    // $content = preg_replace('/"/', '&quot;', $content);
                    // $content = preg_replace('/</', '&lt;' , $content); // $content=preg_replace(' />/', '&gt;', $content);
                    // return $content;

                    $content = str_replace("__apmsign_amp__", "&", $content);
                    $content = str_replace('__plussign_plus__', "+", $content);

                    return $content;
                    }

                    public function decodeContent($content = "")
                    {
                    if (! $content || empty($content) || $content == 'null') {
                    return "null";
                    }

                    return urldecode(htmlspecialchars_decode(base64_decode($content)));
                    }


                    public function developer_file_elements()
                    {
                    $developer_file_elements = [];

                    $componentPath = APPPATH . 'views/components/main';
                    $files = array_diff(scandir($componentPath), ['.', '..']);

                    foreach ($files as $file) {
                    if (pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
                    continue;
                    }
                    // only .blade.php files

                    $fileName = str_replace('.blade', '', pathinfo($file, PATHINFO_FILENAME));
                    $filePath = $componentPath . '/' . $file;

                    $html = file_get_contents($filePath);

                    // Load HTML into DOM
                    $dom = new DOMDocument();
                    libxml_use_internal_errors(true);
                    // $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
                    $dom->loadHTML('
                    <?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                    libxml_clear_errors();

                    $xpath = new DOMXPath($dom);
                    $nodes = $xpath->query('//*[@builder-identity]');

                    $elements = [];

                    foreach ($nodes as $node) {
                    $identity = $node->getAttribute('builder-identity');
                    $tag = $node->nodeName;

                    // Get full element HTML
                    $elementHTML = $dom->saveHTML($node);

                    // Detect src (for images)
                    $src = $node->hasAttribute('src') ? $node->getAttribute('src') : null;

                    // Get inner content (without wrapping tag)
                    $content = '';
                    foreach ($node->childNodes as $child) {
                    $content .= $dom->saveHTML($child);
                    }

                    // 🔹 Get selector path
                    $selector = $this->getDomSelectorPath($node);

                    $elements[$identity] = [
                    'element' => $elementHTML,
                    'tag' => $tag,
                    'identity' => $identity,
                    'selector' => $selector,
                    'content' => $content ? $content : null,
                    'src' => $src ? $src : null,
                    ];
                    }

                    $developer_file_elements[$fileName] = $elements;
                    }

                    return $developer_file_elements;
                    }

                    public function getDomSelectorPath(DOMNode $node)
                    {
                    $path = [];

                    while ($node && $node->nodeType === XML_ELEMENT_NODE && $node->nodeName !== 'html') {
                    $tag = strtolower($node->nodeName);

                    // Find position among siblings of same tag
                    $index = 1;
                    $sibling = $node->previousSibling;
                    while ($sibling) {
                    if ($sibling->nodeType === XML_ELEMENT_NODE && $sibling->nodeName === $node->nodeName) {
                    $index++;
                    }
                    $sibling = $sibling->previousSibling;
                    }

                    // Add nth-of-type if needed
                    $selector = $tag . ($index > 1 ? ":nth-of-type($index)" : '');
                    array_unshift($path, $selector);

                    $node = $node->parentNode;
                    }

                    return 'html > ' . implode(' > ', $path);
                    }
                    // Home page builder

                    

    /**
     * يمنح الأدمن المنشأ حديثا صف صلاحيات فارغا.
     *
     * `is_root_admin()` تعتبر غياب الصف دليل جذر، فالأدمن الذي ينشأ بلا
     * صف يولد سوبر أدمن كامل الصلاحية — ثم ترفض شاشة الصلاحيات تقييده
     * لأنها لا تقيد جذرا. الصف الفارغ يقلب الافتراض: يبدأ بلا شيء،
     * ويمنحه الجذر ما يلزمه.
     *
     * ولا يمس من له صف أصلا، ولا الجذر القائم.
     */
    private function tq_bootstrap_admin_permissions($email)
    {
        $email = trim((string) $email);
        if ($email === '') return;

        $user = $this->db->where('email', $email)->where('role_id', 1)
                         ->order_by('id', 'DESC')->limit(1)
                         ->get('users')->row_array();
        if (!$user) return;

        $has = $this->db->where('admin_id', (int) $user['id'])
                        ->count_all_results('permissions');
        if ($has > 0) return;

        $this->db->insert('permissions', array(
            'admin_id'    => (int) $user['id'],
            'permissions' => json_encode(array()),
        ));
    }

}
