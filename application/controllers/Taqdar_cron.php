<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * مهام تقدر الدورية.
 *
 * **سطر الأوامر فقط.** ولو فتحت من المتصفح لصار انتهاء الاشتراكات فعلا
 * يستطيع أي زائر استدعاءه؛ ولأن الحماية برمز في الرابط تتسرب في السجلات
 * والمحيلات، فالحد هنا على وسيلة الاستدعاء نفسها لا على معرفة سر.
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
     * دورة حياة الحصص كلها — TQ-SESSION-PAY.
     *
     * كانت هذه المهمة تفعل واحدة من أربع: تنهي الطلب بلا رد. وثلاث بقيت
     * بلا كاتب، وكل واحدة منها تترك حالا لا تخرج منها أبدا:
     *
     *   ــ تأكيد بلا دفع يبقى `awaiting_payment` إلى الأبد، والموعد معه.
     *   ــ حصة يحل وقتها ولا يقول جدول اللوحة إنها جارية.
     *   ــ حصة تمر ولا يعلن معلمها انتهاءها: رابطها حي، ونصيبه لا يقيد.
     *
     * والقواعد كلها في `Taqdar_sessions_model::lifecycle_tick()` — هنا
     * نداء وطباعة، فالكرون بابها لا صاحبها. والاسم بقي `expire_sessions`
     * لأن `crontab` الخادم ينادي به، وتغييره يعني مهمة تتوقف بصمت حتى
     * يعدل السطر هناك.
     */
    public function expire_sessions()
    {
        $this->load->model('taqdar_sessions_model');
        $r = $this->taqdar_sessions_model->lifecycle_tick();

        echo date('Y-m-d H:i:s')
           . " sessions_expired={$r['expired_requests']}"
           . " unpaid_expired={$r['expired_unpaid']}"
           . " went_live={$r['went_live']}"
           . " completed={$r['completed']}"
           . " credited_halalas={$r['credited']}\n";
    }

    /** المرادف الذي يقرأ اسمه ما يفعله — للتشغيل اليدوي. */
    public function sessions() { $this->expire_sessions(); }

    /** ينهي الاشتراكات التي مضى أجلها. */
    public function expire()
    {
        $n = $this->taqdar_billing_model->expire_due();
        echo date('Y-m-d H:i:s') . " expired={$n}\n";
    }

    /**
     * يجسد ما استجد من محتوى في صفوف `enrol` — TQ-ENROL-STALE.
     *
     * `sync_enrolments()` تنادى من `activate()` وحدها، فتكتب صورة لحظة
     * الشراء. وما نشر بعدها — مسار جديد، كورس اعتمدته الإدارة، درس
     * أضافه معلم في صف الباقة — لا يبلغ مشتركا قائما: يشاهد دروسه
     * لأن الوصول يستعلم حيا، ويقرأ «لا كورسات بعد» في كل شاشة تضم
     * الجدول. وشاشتان تفترقان أسوأ من عطل ظاهر.
     *
     * والنشر ينادي `resync_scope()` فورا، وهذه شبكة الأمان تحته: تلحق
     * ما فات إن تعثر النداء، وما كتب في القاعدة بيد أو باستيراد.
     *
     * ومأمونة التكرار: `sync_enrolments()` تدرج ما ينقص وتمدد الأجل،
     * ولا تحذف صفا لم تكتبه.
     */
    public function enrolments()
    {
        /* البرامج اليتيمة أولا — TQ-ORPHAN-PURGE.
           برنامج منشور يشير إلى كورس محذوف **يعرض في الكتالوج ويباع**
           ويفتح على «قيد التجهيز» أبدا. وفصله ليس اجتهادا: هو مبيع لا
           يستطيع أن يسلم شيئا، وإبقاؤه معروضا أسوأ من كل ما في إخفائه.
           ولا يحذف — بنود اشتراك مدفوع قد تشير إليه بمعرفه.
           ويترك أثره في السجل: تغيير حالة صف بلا طلب أحد يجب أن يفسر. */
        $d = array('detached' => 0, 'was_published' => 0);
        try {
            $this->load->model('taqdar_purge_model', 'tq_purge');
            $d = $this->tq_purge->detach_orphan_paths();
            if (!empty($d['detached'])) {
                $this->load->model('taqdar_repo_model', 'tq_repo');
                $this->tq_repo->audit(null, 'paths.detach_orphans', 'paths', null, $d);
            }
        } catch (Throwable $e) {
            log_message('error', 'TQ-CRON enrolments/orphans: ' . $e->getMessage());
        }

        $r = $this->taqdar_billing_model->sync_active_enrolments();
        echo date('Y-m-d H:i:s')
           . " orphan_paths={$d['detached']} (was_published={$d['was_published']})"
           . " subs={$r['subscriptions']} changed={$r['changed']} enrolments={$r['enrolments']}\n";
    }

    /**
     * يسأل تاب عن الدفعات التي بدأت ولم تنته.
     *
     * الحال التي تسدها هذه المهمة: يدفع الطالب فيغلق المتصفح قبل أن يعود،
     * ولا يصل الويبهوك — انقطاع، أو خطأ في العنوان، أو صف كتب قبل أن يضبط
     * الموقع HTTPS. فيبقى المال محصلا عند تاب والاشتراك مقفلا، ولا أحد
     * يعلم إلا حين يشتكي صاحبه.
     *
     * والقراءة من تاب لا من الطلب، فتكرارها مأمون: المسوى لا يسوى مرتين
     * (`settle()` ترد فورا على ما حاله `paid`).
     */
    public function reconcile()
    {
        $this->load->model('taqdar_tap_model');
        $r = $this->taqdar_tap_model->reconcile(15, 60);
        echo date('Y-m-d H:i:s') . " tap_checked={$r['checked']} tap_settled={$r['settled']}\n";
    }

    /**
     * ينظف ما لا يقرأ بعد: رموز التحقق المستهلكة وسجل واتساب القديم.
     *
     * والجدولان ينموان بصف لكل محاولة — ومحاولات التسجيل أكثرها لحسابات
     * لم تكتمل، ورسائل واتساب صف لكل إشعار دفع. فبلا تنظيف يصيران أكبر
     * جدولين في القاعدة ولا يقرأ منهما إلا الأيام الأخيرة.
     *
     * وثلاثة أيام للرموز: أطول من عمر الرمز (عشر دقائق) بمراحل، وكافية
     * لتشخيص «سجلت أمس ولم يصلني شيء». وتسعون يوما للسجل: هو ما يجيب
     * عن «هل وصل إشعار دفعة الشهر الماضي؟».
     */
    public function purge()
    {
        $this->load->model('taqdar_otp_model');
        $n = $this->taqdar_otp_model->purge(3);

        $w = 0;
        try {
            $this->load->model('taqdar_wa_model');
            $this->taqdar_wa_model->ensure_schema();
            $this->db->where('at <', date('Y-m-d H:i:s', strtotime('-90 days')))
                     ->delete('tq_wa_log');
            $w = (int) $this->db->affected_rows();
        } catch (Throwable $e) {
            log_message('error', 'TQ-CRON purge: ' . $e->getMessage());
        }

        echo date('Y-m-d H:i:s') . " otp_purged={$n} wa_log_purged={$w}\n";
    }
}
