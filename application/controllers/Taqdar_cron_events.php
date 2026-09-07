<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * أحداث تقدر الدورية.
 *
 * **سطر الأوامر فقط** — على نمط `Taqdar_cron.php` القائم. وليس هذا تشددا:
 * المهمة تكتب إشعارات لأولياء الأمور، ولو فتحت من المتصفح لأمكن أي زائر
 * أن يغرق بريدهم ولوحاتهم بنداء متكرر. والحماية برمز في الرابط تتسرب في
 * سجلات الوصول وفي ترويسة المحيل، فالحد على وسيلة الاستدعاء نفسها.
 *
 * **ولا جلسة هنا.** كل ما تحت هذا الصنف يعمل بلا `$this->session`؛ قراءتها
 * في سطر الأوامر تسقط المهمة بعد أن تكون قد كتبت نصف إشعاراتها.
 *
 * لماذا تشتق أحداث «لحظية» دوريا كذلك؟ لأن مواضع إطلاقها تقع في ملفات
 * لا يملكها هذا العمل (`Taqdar_repo_model::submit_attempt` وغيرها)، ولأن
 * الاشتقاق من البيانات القائمة يجعل المنصة عاملة اليوم لا بعد تعديلها.
 * ومنع التكرار في `Taqdar_events_model` يجعل الطريقتين تتعايشان: من ركب
 * النداء اللحظي لن يصله شيء مكرر من الكرون.
 *
 * التشغيل:
 *   /usr/bin/php /home/taqdaredu.com/cron_taqdar.php taqdar_cron_events run
 *   /usr/bin/php /home/taqdaredu.com/cron_taqdar.php taqdar_cron_events weekly
 */
class Taqdar_cron_events extends CI_Controller
{
    /** حد الخمول بالأيام — ثلاثة، وهو ما تعد به شاشة ولي الأمر نصا. */
    private $inactivity_days = 3;

    /** كم يرجع المسح إلى الوراء بحثا عن أحداث جديدة. */
    private $lookback_days = 2;

    /**
     * ذاكرة منع التكرار: أوسع من نافذة المسح عمدا. لو تساوتا لعاد الحدث
     * نفسه في اليوم التالي بوصفه «جديدا» — والفارق هو ما يمنع ذلك.
     */
    private $memory_days = 14;

    public function __construct()
    {
        parent::__construct();

        if (!$this->input->is_cli_request()) {
            show_404();
        }

        $tz = get_settings('timezone');
        if ($tz) {
            date_default_timezone_set($tz);
        }

        $this->load->database();
        $this->load->model('taqdar_events_model', 'events');
    }

    public function index()
    {
        $this->run();
    }

    /** كل الأحداث اليومية في نداء واحد — وهذا ما يوضع في crontab. */
    public function run()
    {
        $this->inactivity();
        $this->exams();
        $this->stations();
        $this->certificates();
        $this->session_requests();
        $this->placements();
        $this->deliver();
    }

    /**
     * يصرف طابور الإرسال.
     *
     * **بعد** مطلقي الأحداث لا قبلهم: ما أودع في هذه الجولة يخرج فيها لا
     * في التي تليها، فلا يتأخر إشعار ربع ساعة بلا سبب. ومن أجل لساعات
     * الصمت يعاد إيداعه هنا بموعده الجديد ولا يحسب فشلا.
     */
    public function deliver()
    {
        $r = $this->events->drain(80);
        $total = $r['sent'] + $r['failed'] + $r['dead'] + $r['held'] + $r['skipped'];
        $this->line('deliver', $total, $r['sent']);

        /* المؤجل والمتروك والمخفق تقال صراحة: طابور يعرض «أرسل صفرا» ولا
           يقول لماذا يقرأ عطلا — وهو قد يكون ساعة صمت تعمل كما ينبغي، أو
           بريدا مطفأ عمدا. */
        if ($r['held'] || $r['dead'] || $r['failed'] || $r['skipped']) {
            echo '    held=' . $r['held'] . ' retry=' . $r['failed']
               . ' dead=' . $r['dead'] . ' skipped=' . $r['skipped'] . "\n";
        }
    }

    /* =====================================================================
       TQ-LRS — مستودع سجلات التعلم الوطني
       ===================================================================== */

    /**
     * يصرف طابور xAPI.
     *
     * فعل مستقل لا جزء من `run()`: الاخير يعمل مرة في اليوم، ورحلة
     * المتعلم تقاس عند الجهة بقربها من وقتها. فهذا يوضع في الكرون كل خمس دقائق.
     */
    public function lrs($limit = 60)
    {
        $this->lrs_sweep();
        $this->load->model('taqdar_lrs_model', 'lrs');
        $r = $this->lrs->drain((int) $limit);
        $total = $r['sent'] + $r['failed'] + $r['dead'] + $r['skipped'] + $r['held'];
        $this->line('lrs', $total, $r['sent']);
        if ($r['failed'] || $r['dead'] || $r['skipped'] || $r['held']) {
            echo '    retry=' . $r['failed'] . ' dead=' . $r['dead']
               . ' skipped=' . $r['skipped'] . ' held=' . $r['held'] . "\n";
        }
    }

    /**
     * يشتق الأحداث من الجداول ويودعها الطابور.
     *
     * قبل `lrs()` في الترتيب: ما اشتق في هذه الجولة يخرج فيها لا في التي
     * تليها — وهو حكم `deliver()` نفسه.
     */
    public function lrs_sweep($limit = 200)
    {
        $this->load->model('taqdar_lrs_model', 'lrs');
        $r = $this->lrs->sweep((int) $limit);
        $n = array_sum($r);
        $this->line('lrs_sweep', $n, $n);
        if ($n) {
            $bits = array();
            foreach ($r as $k => $v) if ($v) $bits[] = $k . '=' . $v;
            echo '    ' . implode(' ', $bits) . "\n";
        }
    }

    /** يسأل المستودع عن نفسه — قراءة لا كتابة، فلا تترك اثرا عندهم. */
    public function lrs_diagnose()
    {
        $this->load->model('taqdar_lrs_model', 'lrs');
        $d = $this->lrs->diagnose();
        echo ($d['ok'] ? '✓' : '✗') . ' ' . $d['endpoint'] . "\n";
        echo '  code=' . $d['code'] . ' — ' . $d['note'] . "\n";

        $q = $this->lrs->queue_stats();
        echo '  الطابور: ';
        foreach ($q as $k => $n) if ($k !== 'total') echo $k . '=' . $n . ' ';
        echo '· المجموع=' . $q['total'] . "\n";
    }

    /**
     * يطبع الرسائل الثماني بقيم نموذجية — لمقارنتها بالعقد حرفا.
     *
     * ولا يكتب في الطابور ولا يرسل: الفحص هنا على **شكل** الرسالة، وهو
     * الشيء الوحيد الذي يمكن فحصه ما دام المستودع محجوبا.
     */
    public function lrs_dump($uid = 0)
    {
        $this->load->model('taqdar_lrs_model', 'lrs');

        /* بمعرّف مستخدم: يقرأ المتعلم من القاعدة فيظهر الفاعل كما يخرج
           فعلا — وهو ما يفحص به وجود رقم الهوية من عدمه. وبلا معرّف:
           قيم نموذجية لفحص الشكل وحده. */
        $u = $this->lrs->learner((int) $uid);
        if (!$u) {
            $u = array('id' => 0, 'email' => 'student@example.com',
                       'national_id' => '1012345678', 'phone' => '', 'full_name' => 'طالب');
        }
        $crs = array('url' => 'path/alryadyat-21', 'title' => 'برنامج الرياضيات',
                     'desc' => 'الأعداد النسبية وعملياتها');
        $ins = array('name' => 'أحمد عبدالله', 'email' => 'teacher@taqdaredu.com');

        $cases = array(
            'registered' => array('course' => $crs, 'instructor' => $ins,
                'duration' => 'PT10H', 'mobile' => '966555123456',
                'full_name' => 'طالب تجريبي الثاني', 'nationality' => 'SA', 'dob' => '2012-06-15'),
            'initialized' => array('course' => $crs, 'instructor' => $ins),
            'watched' => array('course' => $crs, 'instructor' => $ins,
                'lesson' => array('url' => 'student/lesson/21/119', 'title' => 'الدرس الأول',
                                  'desc' => 'مقدمة'),
                'completed' => true, 'duration' => 'PT12M30S',
                'browser' => array('os' => 'Windows', 'name' => 'Google Chrome', 'version' => '140.0')),
            'attempted' => array('course' => $crs, 'instructor' => $ins,
                'quiz' => array('url' => 'student/quiz/41', 'title' => 'اختبار الوحدة الأولى',
                                'desc' => 'عشرة أسئلة'),
                'attempt_no' => 1, 'raw' => 80, 'min' => 0, 'max' => 100, 'passed' => true,
                'browser' => array('os' => 'Windows', 'name' => 'Google Chrome', 'version' => '140.0')),
            'completed_lesson' => array('course' => $crs, 'instructor' => $ins,
                'lesson' => array('url' => 'student/lesson/21/119', 'title' => 'الدرس الأول',
                                  'desc' => 'مقدمة'),
                'duration' => 'PT14M0S',
                'browser' => array('os' => 'Windows', 'name' => 'Google Chrome', 'version' => '140.0')),
            'completed_unit' => array('course' => $crs, 'instructor' => $ins,
                'unit' => array('url' => 'path/alryadyat-21#unit-3', 'title' => 'الوحدة الثالثة',
                                'desc' => '')),
            'completed_course' => array('course' => $crs, 'instructor' => $ins),
            'progressed' => array('course' => $crs, 'instructor' => $ins, 'percent' => 40),
            'rated' => array('course' => $crs, 'instructor' => $ins, 'stars' => 4,
                             'comment' => 'شرح واضح'),
            'earned' => array('course' => $crs,
                'certificate' => array('url' => 'certificate/57',
                                       'title' => 'شهادة إتمام برنامج الرياضيات',
                                       'file_url' => 'certificate/57')),
        );

        foreach ($cases as $ev => $args) {
            $args['user'] = $u;
            $st = $this->lrs->build($ev, $args);
            echo "\n══════ " . $ev . " ══════\n";
            echo json_encode($st, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), "\n";
        }
    }

    /**
     * يولّد رحلة التحقّق العشر لمتعلّم ومقرّر.
     *
     *   … taqdar_cron_events lrs_journey <معرّف المتعلّم> <معرّف المقرّر> [force]
     *
     * ويطبع القيمتين اللتين تكتبان في نموذج «تشغيل اختبار التحقق» عند
     * الجهة — فلا يقرأ أحد الطابور ليعرف ماذا يلصق.
     */
    public function lrs_journey($uid = 0, $cid = 0, $force = '')
    {
        $this->load->model('taqdar_lrs_model', 'lrs');
        $r = $this->lrs->journey((int) $uid, (int) $cid, ($force === 'force'));

        if (empty($r['ok'])) { echo '✗ ' . $r['error'] . "\n"; return; }

        echo "✓ رحلة التحقّق\n";
        echo '  رقم الهوية   : ' . ($r['actor'] !== '' ? $r['actor'] : '(لا هوية لهذا المتعلّم)') . "\n";
        echo '  معرّف الدورة : ' . $r['object'] . "\n";
        echo '  كتبت        : ' . $r['written'] . " رسالة جديدة\n";

        $rows = $this->lrs->journey_state((int) $uid, (int) $cid);
        echo '  الخطوات (' . count($rows) . "):\n";
        foreach ($rows as $i => $q) {
            printf("    %2d. %-18s %s\n", $i + 1, $q['verb'], $q['state']);
        }
    }

    /** يعيد الميت والمتروك الى الطابور — بعد فتح الحجب مثلا. */
    public function lrs_revive()
    {
        $this->load->model('taqdar_lrs_model', 'lrs');
        echo 'أعيد ' . $this->lrs->revive() . ' صفا إلى الطابور.' . "\n";
    }

    /* =====================================================================
       الخمول — الحدث الدوري الأصيل
       ===================================================================== */

    /**
     * طالب بلا نشاط ثلاثة أيام ⇒ إشعار لوليه.
     *
     * ومن لم يبدأ قط يقاس خموله من تاريخ تسجيله لا من الصفر، وإلا صار
     * كل حساب جديد «منقطعا منذ ١٩٧٠». ونافذة منع التكرار ثلاثة أيام لا
     * يوم: الانقطاع حالة مستمرة، وتكرار التنبيه بها يوميا هو بعينه ما
     * يجعل ولي الأمر يطفئ الإشعارات كلها.
     */
    public function inactivity()
    {
        $cut     = time() - $this->inactivity_days * 86400;
        $written = 0;
        $matched = 0;

        foreach ($this->events->watched_students() as $student_id) {
            $last = $this->events->last_activity_at($student_id);
            if ($last <= 0) {
                $last = $this->events->registered_at($student_id);
            }
            if ($last <= 0 || $last > $cut) {
                continue;
            }
            $matched++;

            $days = (int) floor((time() - $last) / 86400);
            $written += $this->events->notify_parents($student_id, 'inactivity_3days', array(
                'key'         => 'inactive',
                'window_days' => $this->inactivity_days,
                'text'        => 'لم يسجل {name} أي نشاط دراسي منذ ' . $this->days_ar($days) . '. '
                               . 'أقصر طريق للعودة درس واحد قصير، لا خطة أسبوع.',
            ));
        }

        $this->line('inactivity', $matched, $written);
    }

    /* =====================================================================
       أحداث تشتق من البيانات القائمة
       ===================================================================== */

    /** نتيجة امتحان: كل محاولة امتحان سلمت حديثا — نجحت أو رسبت. */
    public function exams()
    {
        $written = 0;
        $rows    = $this->recent_exam_attempts();

        foreach ($rows as $r) {
            $label  = trim((string) $r['label']);
            $out_of = (int) $r['out_of'];

            // «8 من 0» رقم كاذب: عدد الأسئلة يقرأ من الإجابات المسجلة،
            // وحين لا تسجل تذكر الدرجة وحدها بلا مقام مخترع.
            $score = ($out_of > 0)
                ? ((int) $r['score'] . ' من ' . $out_of)
                : ('الدرجة ' . (int) $r['score']);

            // نص محايد الضمير: الإشعار نفسه يصل الطالب ووليه، و«اجتازه»
            // تصير في شاشة الطالب حديثا عن غائب.
            $text = 'نتيجة ' . ($label !== '' ? 'امتحان «' . $label . '»' : 'الامتحان') . ': '
                  . $score
                  . ((int) $r['passed'] === 1 ? ' — اجتياز.' : ' — دون حد النجاح.');

            $written += $this->events->notify_student_and_parents((int) $r['student_id'], 'exam_result', array(
                'key'         => 'attempt:' . (int) $r['id'],
                'window_days' => $this->memory_days,
                'text'        => $text,
            ));
        }

        $this->line('exam_result', count($rows), $written);
    }

    /**
     * رسوب في اختبار محطة.
     *
     * «المحطة» في تقدر هي `milestones`، واختبارها هو التقييم المرتبط بها —
     * إما بـ`assessments.milestone_id` وإما بـ`milestones.checkpoint_assessment_id`.
     * والوجهان مقروءان معا لأن الجدولين يسمحان بالربط من أي طرف، والاكتفاء
     * بأحدهما يسقط نصف الحالات صامتا.
     */
    public function stations()
    {
        $since = date('Y-m-d H:i:s', time() - $this->lookback_days * 86400);

        $rows = $this->db->query(
            "SELECT a.`id`, a.`student_id`, a.`score`, a.`attempt_no`,
                    (SELECT m.`title` FROM `milestones` m
                      WHERE m.`id` = s.`milestone_id` OR m.`checkpoint_assessment_id` = s.`id`
                      LIMIT 1) AS label
               FROM `attempts` a
               JOIN `assessments` s ON s.`id` = a.`assessment_id`
              WHERE a.`passed` = 0
                AND a.`submitted_at` IS NOT NULL
                AND a.`submitted_at` >= ?
                AND (s.`milestone_id` IS NOT NULL
                     OR EXISTS (SELECT 1 FROM `milestones` m2
                                 WHERE m2.`checkpoint_assessment_id` = s.`id`))",
            array($since)
        )->result_array();

        $written = 0;
        foreach ($rows as $r) {
            $label = trim((string) $r['label']);
            $written += $this->events->notify_student_and_parents((int) $r['student_id'], 'station_failed', array(
                'key'         => 'attempt:' . (int) $r['id'],
                'window_days' => $this->memory_days,
                'text'        => 'اختبار محطة' . ($label !== '' ? ' «' . $label . '»' : '')
                               . ': المحاولة رقم ' . (int) $r['attempt_no'] . ' لم تبلغ حد النجاح. '
                               . 'المحطة لا تفتح بالمحاولة بل بالإتقان، والإعادة متاحة بلا حد.',
            ));
        }

        $this->line('station_failed', count($rows), $written);
    }

    /**
     * شهادة جديدة.
     *
     * تعريف الشهادة هنا هو تعريف `tq_certificates.php` حرفا بحرف: اجتياز
     * امتحان (`assessments.type = 'exam'` و`attempts.passed = 1`). ولو
     * اختلف التعريفان لظهرت للطالب شهادة لا يعرف بها وليه، أو العكس.
     */
    public function certificates()
    {
        $written = 0;
        $rows    = $this->recent_exam_attempts(1);

        foreach ($rows as $r) {
            $label = trim((string) $r['label']);
            $written += $this->events->notify_student_and_parents((int) $r['student_id'], 'certificate', array(
                'key'         => 'attempt:' . (int) $r['id'],
                'window_days' => $this->memory_days,
                'text'        => 'شهادة إتقان' . ($label !== '' ? ' «' . $label . '»' : '')
                               . ' صارت متاحة، وتحمل رمز تحقق يفتح صفحة عامة تؤكد صحتها.',
            ));
        }

        $this->line('certificate', count($rows), $written);
    }

    /**
     * طلب حصة خاصة.
     *
     * `tutoring_sessions` بلا عمود زمن، فالمسح على الحالة `requested` وحدها
     * وذاكرة منع التكرار شهر: الطلب المعلق لا يعاد التنبيه به كل ليلة.
     * ويصل الإشعار المعلم أيضا — فهو الطرف المطلوب منه الرد.
     */
    public function session_requests()
    {
        $rows = $this->db->query(
            "SELECT t.`id`, t.`student_id`, t.`teacher_id`,
                    (SELECT o.`text` FROM `objectives` o WHERE o.`id` = t.`context_objective_id`) AS objective
               FROM `tutoring_sessions` t
              WHERE t.`status` = 'requested'"
        )->result_array();

        $written = 0;
        foreach ($rows as $r) {
            $why  = trim((string) $r['objective']);
            $text = 'طلبت حصة خاصة' . ($why !== '' ? ' بسبب هدف «' . $why . '»' : '') . '، وتنتظر رد المعلم.';

            $written += $this->events->notify_student_and_parents((int) $r['student_id'], 'session_request', array(
                'key'         => 'session:' . (int) $r['id'],
                'window_days' => 30,
                'text'        => $text,
            ));

            $teacher_id = (int) $r['teacher_id'];
            if ($teacher_id > 0) {
                $written += ($this->events->notify($teacher_id, 'session_request', array(
                    'key'         => 'session:' . (int) $r['id'],
                    'window_days' => 30,
                    'title'       => 'طلب حصة خاصة بانتظار ردك',
                    'text'        => $text,
                )) > 0) ? 1 : 0;
            }
        }

        $this->line('session_request', count($rows), $written);
    }

    /**
     * نتائج تحديد المستوى التي لم يبلغ بها أحد.
     *
     * وهي المهمة الوحيدة هنا التي **لا تشتق** حدثا من البيانات: النتيجة
     * تبلغ لحظة التسليم من `Taqdar.php::placement_submit()`، وهذا المسح
     * لما لم يخرج حينها. وثلاث حالات تسقط في هذا الطابور:
     *
     *   · البريد الصادر لم يكن مضبوطا وقت التسليم — وهو حال هذا الخادم
     *     حتى تكتب كلمة المرور في `taqdar_admin/mail`. فما أدي قبل الضبط
     *     يصل بعده، ولا يضيع صامتا.
     *   · الخادم رفض الرسالة (انقطاع · حد إرسال · اعتماد مؤقت).
     *   · ولي الأمر ربط حسابه بعد أن أدى ابنه الاختبار.
     *
     * و`notify_result()` تدمغ `notified_at` فيما أرسل، فلا يعاد على أحد.
     * ونافذتها أربعة عشر يوما — بعدها لا تبلغ نتيجة لا يذكرها صاحبها.
     */
    public function placements()
    {
        $this->load->model('taqdar_diag_model');

        $ids  = $this->taqdar_diag_model->pending_notifications($this->memory_days, 200);
        $sent = 0;

        foreach ($ids as $id) {
            $r = $this->taqdar_diag_model->notify_result($id);
            if ((int) $r['sent'] > 0) {
                $sent++;
            }
        }

        $this->line('placement_result', count($ids), $sent);
    }

    /* =====================================================================
       التقرير الأسبوعي
       ===================================================================== */

    /**
     * تقرير الأحد.
     *
     * الشاشة `tq_parent_weekly.php` تعرض التقرير ولا شيء كان يرسله. وهذا
     * ما يرسله — داخل المنصة أولا، والبريد خلف مفتاحه المطفأ.
     *
     * ومصادر الأرقام هي مصادر الشاشة نفسها (`watch_histories`,
     * `quiz_results`)، فلا يقول الإشعار رقما تكذبه الصفحة التي يفتحها.
     * وإشعار واحد لولي الأمر يجمع أبناءه لا إشعار لكل ابن: التقرير
     * الأسبوعي ملخص، ولو تعدد لصار قائمة.
     */
    public function weekly()
    {
        $week_start = strtotime('today') - ((int) date('w')) * 86400; // الأسبوع يبدأ الأحد
        $prev_start = $week_start - 7 * 86400;

        $parents = $this->db->query(
            'SELECT DISTINCT pl.`parent_user_id` AS id
               FROM `parent_links` pl
               JOIN `users` u ON u.`id` = pl.`parent_user_id` AND u.`status` = 1
              WHERE pl.`status` = "active"'
        )->result_array();

        $written = 0;
        foreach ($parents as $p) {
            $parent_id = (int) $p['id'];

            /* من أوقف تقرير الأحد لا يصله.
               الخانة في شاشة الإعدادات كانت تحفظ ولا تقرأ: يوقف التقرير
               فيصله كل أحد كما كان — وهو أكثر ما يشكى منه في بريد أسبوعي. */
            if (!$this->events->parent_wants($parent_id, 'weekly_report')) {
                continue;
            }

            $children = $this->db->query(
                'SELECT u.`id`, u.`first_name`, u.`last_name`
                   FROM `parent_links` pl
                   JOIN `users` u ON u.`id` = pl.`student_id`
                  WHERE pl.`parent_user_id` = ? AND pl.`status` = "active"
                  ORDER BY u.`first_name` ASC',
                array($parent_id)
            )->result_array();

            if (!$children) {
                continue;
            }

            $lines = array();
            foreach ($children as $c) {
                $sid  = (int) $c['id'];
                $name = trim($c['first_name'] . ' ' . $c['last_name']);
                /* المقارنة على مدى واحد: ما مضى من هذا الأسبوع مقابل
                   **الأيام نفسها** من الأسبوع الماضي. وكانت تقارنه
                   بالأسبوع الماضي كاملا — والتقرير يرسل صباح الأحد،
                   فيقرأ كل ولي أمر أن نشاط ابنه «أقل من الأسبوع الماضي»
                   لأن أسبوعا لم يبدأ يقارن بأسبوع تم. */
                $elapsed = ((int) date('w') + 1) * 86400;
                $now  = $this->active_days($sid, $week_start, PHP_INT_MAX);
                $was  = $this->active_days($sid, $prev_start, $prev_start + $elapsed);
                $qz   = $this->quizzes_between($sid, $week_start, PHP_INT_MAX);

                $trend = ($now > $was) ? 'أفضل من الأسبوع الماضي'
                       : (($now < $was) ? 'أقل من الأسبوع الماضي' : 'كأسبوعه الماضي');

                // أرقام هندية وعربية في سطر واحد تقرأ سطرين. الكل غربي هنا،
                // وعزلها بصريا شأن الشاشة لا شأن نص مخزن.
                $lines[] = ($name !== '' ? $name : 'ابنك') . ': '
                         . ($now > 0 ? 'درس في ' . $now . ' من 7 أيام' : 'لم يدرس أي يوم')
                         . ' (' . $trend . ')، و' . $this->quizzes_ar($qz) . '.';
            }

            $written += ($this->events->notify($parent_id, 'weekly_report', array(
                'key'         => 'week:' . $week_start,
                'window_days' => 6,
                'title'       => 'تقريرك الأسبوعي جاهز',
                'text'        => implode(' ', $lines),
            )) > 0) ? 1 : 0;
        }

        $this->line('weekly_report', count($parents), $written);
    }

    /* =====================================================================
       الداخل
       ===================================================================== */

    /**
     * محاولات الامتحانات المسلمة حديثا.
     * @param int|null $passed 1 للناجحة وحدها، أو null للكل.
     */
    private function recent_exam_attempts($passed = null)
    {
        $since  = date('Y-m-d H:i:s', time() - $this->lookback_days * 86400);
        $params = array($since);

        $filter = '';
        if ($passed !== null) {
            $filter  = ' AND a.`passed` = ?';
            $params[] = (int) $passed;
        }

        return $this->db->query(
            "SELECT a.`id`, a.`student_id`, a.`score`, a.`passed`,
                    COALESCE(s.`pass_mark`, 0) AS pass_mark,
                    (SELECT COUNT(*) FROM `answers` an WHERE an.`attempt_id` = a.`id`) AS out_of,
                    COALESCE(m.`title`, p.`title`, '') AS label
               FROM `attempts` a
               JOIN `assessments` s ON s.`id` = a.`assessment_id` AND s.`type` = 'exam'
               LEFT JOIN `milestones` m ON m.`id` = s.`milestone_id`
               LEFT JOIN `paths` p ON p.`id` = COALESCE(s.`path_id`, m.`path_id`)
              WHERE a.`submitted_at` IS NOT NULL
                AND a.`submitted_at` >= ?" . $filter,
            $params
        )->result_array();
    }

    /**
     * TQ-QUIZ-WEEKLY — عدد الاختبارات في المدّة.
     *
     * كانت تعدّ من `quiz_results` — جدول النظام الموروث. ونتائج اختبار
     * الدرس تُكتب في `attempts`، فكان التقرير الأسبوعيّ يقول لوليّ الأمر
     * «**ولم يؤدِّ أيّ اختبار**» وابنه أدّى خمسة في الأسبوع نفسه. والعلّة
     * نفسها كانت في `tq_s_quizzes()` وأُصلحت هناك، ونُسيت هنا.
     *
     * والعدّ من الاثنين: المسلَّمة في `attempts` (بوّابة الإتقان)، وما بقي
     * في `quiz_results` للموروث — فلا يسقط تاريخٌ قديم.
     */
    private function quizzes_between($student_id, $from_ts, $to_ts)
    {
        $n = 0;
        try {
            $r = $this->db->query(
                'SELECT COUNT(*) AS n FROM `attempts`
                  WHERE `student_id` = ? AND `submitted_at` IS NOT NULL
                    AND `submitted_at` >= ? AND `submitted_at` < ?',
                array((int) $student_id, date('Y-m-d H:i:s', (int) $from_ts),
                      date('Y-m-d H:i:s', (int) $to_ts)))->row_array();
            $n += (int) $r['n'];
        } catch (Throwable $e) {
            log_message('error', 'quizzes_between attempts: ' . $e->getMessage());
        }
        try {
            $r = $this->db->query(
                'SELECT COUNT(*) AS n FROM `quiz_results`
                  WHERE `user_id` = ? AND `is_submitted` = 1
                    AND CAST(`date_added` AS UNSIGNED) >= ?
                    AND CAST(`date_added` AS UNSIGNED) < ?',
                array((int) $student_id, (int) $from_ts, (int) $to_ts))->row_array();
            $n += (int) $r['n'];
        } catch (Throwable $e) {
            log_message('error', 'quizzes_between legacy: ' . $e->getMessage());
        }
        return $n;
    }

    /**
     * عدد الأيام بصيغة عربية سليمة.
     * «منذ 10 يوما» خطأ ظاهر لولي الأمر: من ٣ إلى ١٠ جمع قلة (أيام)،
     * وما بعدها تمييز مفرد منصوب (يوما). والنص الذي يخطئ في هذا يقرأ
     * على أنه آلي فيهمل.
     */
    private function days_ar($n)
    {
        $n = (int) $n;
        if ($n === 1) return 'يوم واحد';
        if ($n === 2) return 'يومين';
        if ($n <= 10) return $n . ' أيام';
        return $n . ' يوما';
    }

    /** عدد الاختبارات بصيغة عربية سليمة — لا «0 اختبارا» ولا «2 اختبارا». */
    private function quizzes_ar($n)
    {
        $n = (int) $n;
        if ($n === 0) return 'لم ينه أي اختبار';
        if ($n === 1) return 'أنهى اختبارا واحدا';
        if ($n === 2) return 'أنهى اختبارين';
        if ($n <= 10) return 'أنهى ' . $n . ' اختبارات';
        return 'أنهى ' . $n . ' اختبارا';
    }

    /** سطر واحد لكل حدث: المرشح والمكتوب — والفارق بينهما هو منع التكرار. */
    private function line($event, $candidates, $written)
    {
        echo date('Y-m-d H:i:s') . ' ' . $event
           . ' candidates=' . (int) $candidates
           . ' written=' . (int) $written . "\n";
    }
}
