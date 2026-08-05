<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * مهامّ تقدّر الدورية.
 *
 * **سطر الأوامر فقط.** ولو فُتحت من المتصفّح لصار انتهاء الاشتراكات فعلًا
 * يستطيع أي زائر استدعاءه؛ ولأن الحماية برمز في الرابط تتسرّب في السجلّات
 * والمُحيلات، فالحدّ هنا على وسيلة الاستدعاء نفسها لا على معرفة سرّ.
 */
class Taqdar_cron extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        if (!$this->input->is_cli_request()) {
            show_404();
        }

        date_default_timezone_set(get_settings('timezone'));
        $this->load->database();
        $this->load->model('taqdar_billing_model');
    }

    public function index()
    {
        $this->expire();
    }

    /**
     * يُنهي طلبات الحصص التي لم يردّ عليها معلّمها خلال ٢٤ ساعة،
     * ويُعيد موعدها متاحًا. النصّ المعروض للطالب يَعِد بهذا، ولم يكن
     * شيءٌ يكتبه — فالطلب يبقى «معلّقًا» أبدًا ويحجز موعدًا لا يُستعمل.
     */
    public function expire_sessions()
    {
        $cut = date('Y-m-d H:i:s', strtotime('-24 hours'));

        $rows = $this->db->select('ts.id, ts.slot_id')
                         ->from('tutoring_sessions ts')
                         ->join('availability_slots av', 'av.id = ts.slot_id', 'left')
                         ->where('ts.status', 'requested')
                         ->where('av.starts_at <', $cut)
                         ->get()->result_array();

        $n = 0;
        foreach ($rows as $r) {
            $this->db->where('id', (int) $r['id'])
                     ->where('status', 'requested')
                     ->update('tutoring_sessions', array('status' => 'expired'));
            if ($this->db->affected_rows() > 0) {
                $n++;
                // الموعد يعود متاحًا: حجزٌ لطلبٍ ميّت يُضيّق جدول المعلّم بلا سبب
                $this->db->where('id', (int) $r['slot_id'])
                         ->where('status', 'held')
                         ->update('availability_slots', array('status' => 'open'));
            }
        }

        echo date('Y-m-d H:i:s') . " sessions_expired={$n}\n";
    }

    /** ينهي الاشتراكات التي مضى أجلها. */
    public function expire()
    {
        $n = $this->taqdar_billing_model->expire_due();
        echo date('Y-m-d H:i:s') . " expired={$n}\n";
    }
}
