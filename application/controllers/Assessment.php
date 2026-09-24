<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Public entrance only; existing authenticated assessment handles answers/results. */
class Assessment extends CI_Controller
{
    public function index()
    {
        $this->load->database();
        $this->load->library('session');
        $this->load->model('taqdar_diag_model');
        $exams = $this->db->select('e.*, g.name_ar AS grade_name')
            ->from('tq_diag_exams e')->join('grades g', 'g.id=e.grade_id')
            ->where('e.status', 'published')->where('g.active', 1)
            ->order_by('g.order', 'ASC')->get()->result_array();
        $available = array();
        foreach ($exams as $exam) {
            if ($this->taqdar_diag_model->readiness($exam)['ok']) $available[] = $exam;
        }
        $this->output->set_header('Cache-Control: no-store');
        $this->load->view('frontend/taqdar/index', array(
            'page_name' => 'site_assessment',
            'page_title' => 'اختبار تحديد المستوى المجاني',
            'assessment_grades' => $available,
        ));
    }
}
