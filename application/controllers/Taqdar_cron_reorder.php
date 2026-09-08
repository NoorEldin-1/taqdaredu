<?php
/* ينقل «اختبار الوحدة» إلى آخر ترتيب قسمه — عبر المسار المعتمَد وحده. */
defined('BASEPATH') or exit('No direct script access allowed');
class Taqdar_cron_reorder extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!$this->input->is_cli_request()) show_404();
        $this->load->database();
        $this->load->model('taqdar_curriculum_model', 'curr');
    }

    public function index($section_id = 0, $apply = '')
    {
        $sid = (int) $section_id;
        $rows = $this->db->select('id, title, `order`', false)->where('section_id', $sid)
                         ->order_by('`order`', 'ASC', false)->get('lesson')->result_array();
        if (!$rows) { echo "لا دروس في القسم $sid.\n"; return; }

        /* الاختبارات إلى الذيل، والباقي على ترتيبه. */
        $rest = array(); $exams = array();
        foreach ($rows as $r) {
            if (mb_strpos($r['title'], 'اختبار الوحدة') === 0) $exams[] = $r; else $rest[] = $r;
        }
        $final = array_merge($rest, $exams);

        echo "الترتيب المقترَح للقسم $sid:\n";
        $ids = array();
        foreach ($final as $i => $r) {
            $ids[] = (int) $r['id'];
            printf("  %d. #%-5d %s%s\n", $i + 1, $r['id'], $r['title'],
                   ((int) $r['order'] !== $i + 1) ? '   ← كان ' . $r['order'] : '');
        }
        if ($apply !== 'apply') { echo "\nمعاينة — أضف apply للتنفيذ.\n"; return; }

        $u = $this->db->select('id')->where('role_id', 1)->where('status', 1)
                      ->order_by('id', 'ASC')->limit(1)->get('users')->row_array();
        $r = $this->curr->sort_lessons($this->curr->actor_as('admin', $u ? (int) $u['id'] : 0), $sid, $ids);
        echo "\n" . (empty($r['ok']) ? '✗ ' : '✓ ') . $r['message'] . "\n";
    }
}
