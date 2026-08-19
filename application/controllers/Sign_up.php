<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Sign_up extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        
        date_default_timezone_set(get_settings('timezone'));

        // Your own constructor code
        $this->load->database();
        $this->load->library('session');
        /*cache control*/
        $this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        $this->output->set_header('Pragma: no-cache');

        //Check custom session data
        $this->user_model->check_session_data();
    }

    public function index()
    {
         if (get_settings('public_signup') != 'enable') {
             redirect(site_url(), 'refresh');
            return;
        }

        if ($this->session->userdata('admin_login')) {
            redirect(site_url('admin'), 'refresh');
        } elseif ($this->session->userdata('user_login')) {
            redirect(site_url('user'), 'refresh');
        }
        $page_data['page_name'] = 'sign_up';
        $page_data['page_title'] = site_phrase('sign_up');
        $this->load->view('frontend/' . get_frontend_settings('theme') . '/index', $page_data);
    }

    /**
     * شاشة تأكيد الحساب بالرمز.
     *
     * الحارس على `tq_otp` لا على `register_email` وحده: الأولى هي جلسة
     * التأكيد الجارية (فيها الهوية والقنوات ومن يفتح)، والثانية بقية من
     * المسار الموروث يقرؤها ثيم Academy. فمن وصل بالمفتاح القديم وحده —
     * أو بقي في تبويب مفتوح بعد أن أكد — يعاد إلى التسجيل بدل أن يرى
     * نموذجا لا يقبل منه شيئا.
     */
    public function verification_code()
    {
        $otp = $this->session->userdata('tq_otp');
        if (!is_array($otp) || empty($otp['identity'])) {
            if (!$this->session->userdata('register_email')) {
                redirect(site_url('sign_up'), 'refresh');
                return;
            }
        }

        $page_data['page_name']  = "verification_code";
        $page_data['page_title'] = 'تأكيد الحساب';
        $this->load->view('frontend/' . get_frontend_settings('theme') . '/index', $page_data);
    }

}
