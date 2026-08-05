<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * أحداث تقدّر الدورية.
 *
 * **سطر الأوامر فقط** — على نمط `Taqdar_cron.php` القائم. وليس هذا تشدّدًا:
 * المهمّة تكتب إشعارات لأولياء الأمور، ولو فُتحت من المتصفّح لأمكن أي زائر
 * أن يغرق بريدهم ولوحاتهم بنداء متكرّر. والحماية برمز في الرابط تتسرّب في
 * سجلّات الوصول وفي ترويسة المُحيل، فالحدّ على وسيلة الاستدعاء نفسها.
 *
 * **ولا جلسة هنا.** كل ما تحت هذا الصنف يعمل بلا `$this->session`؛ قراءتها
 * في سطر الأوامر تُسقط المهمّة بعد أن تكون قد كتبت نصف إشعاراتها.
 *
 * لماذا تُشتقّ أحداث «لحظية» دوريًّا كذلك؟ لأن مواضع إطلاقها تقع في ملفّات
 * لا يملكها هذا العمل (`Taqdar_repo_model::submit_attempt` وغيرها)، ولأن
 * الاشتقاق من البيانات القائمة يجعل المنصّة عاملة اليوم لا بعد تعديلها.
 * ومنع التكرار في `Taqdar_events_model` يجعل الطريقتين تتعايشان: مَن ركّب
 * النداء اللحظي لن يصله شيء مكرّر من الكرون.
 *
 * التشغيل:
 *   /usr/bin/php /home/taqdaredu.com/cron_taqdar.php taqdar_cron_events run
 *   /usr/bin/php /home/taqdaredu.com/cron_taqdar.php taqdar_cron_events weekly
 */
class Taqdar_cron_events extends CI_Controller
{
    /** حدّ الخمول بالأيام — ثلاثة، وهو ما تَعِد به شاشة وليّ الأمر نصًّا. */
    private $inactivity_days = 3;

    /** كم يرجع المسح إلى الوراء بحثًا عن أحداث جديدة. */
    private $lookback_days = 2;

    /**
     * ذاكرة منع التكرار: أوسع من نافذة المسح عمدًا. لو تساوتا لعاد الحدث
     * نفسه في اليوم التالي بوصفه «جديدًا» — والفارق هو ما يمنع ذلك.
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

    /** كل الأحداث اليومية في نداء واحد — وهذا ما يُوضع في crontab. */
    public function run()
    {
        $this->inactivity();
        $this->exams();
        $this->stations();
        $this->certificates();
        $this->session_requests();
    }

    /* =====================================================================
       الخمول — الحدث الدوري الأصيل
       ===================================================================== */

    /**
     * طالب بلا نشاط ثلاثة أيام ⇒ إشعار لوليّه.
     *
     * ومَن لم يبدأ قطّ يُقاس خموله من تاريخ تسجيله لا من الصفر، وإلّا صار
     * كل حساب جديد «منقطعًا منذ ١٩٧٠». ونافذة منع التكرار ثلاثة أيام لا
     * يوم: الانقطاع حالة مستمرّة، وتكرار التنبيه بها يوميًّا هو بعينه ما
     * يجعل وليّ الأمر يطفئ الإشعارات كلّها.
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
                'text'        => 'لم يسجّل {name} أي نشاط دراسي منذ ' . $this->days_ar($days) . '. '
                               . 'أقصر طريق للعودة درس واحد قصير، لا خطّة أسبوع.',
            ));
        }

        $this->line('inactivity', $matched, $written);
    }

    /* =====================================================================
       أحداث تُشتقّ من البيانات القائمة
       ===================================================================== */

    /** نتيجة امتحان: كل محاولة امتحان سُلِّمت حديثًا — نجحت أو رسبت. */
    public function exams()
    {
        $written = 0;
        $rows    = $this->recent_exam_attempts();

        foreach ($rows as $r) {
            $label  = trim((string) $r['label']);
            $out_of = (int) $r['out_of'];

            // «8 من 0» رقم كاذب: عدد الأسئلة يُقرأ من الإجابات المسجَّلة،
            // وحين لا تُسجَّل تُذكر الدرجة وحدها بلا مقام مخترع.
            $score = ($out_of > 0)
                ? ((int) $r['score'] . ' من ' . $out_of)
                : ('الدرجة ' . (int) $r['score']);

            // نصّ محايد الضمير: الإشعار نفسه يصل الطالبَ ووليَّه، و«اجتازه»
            // تصير في شاشة الطالب حديثًا عن غائب.
            $text = 'نتيجة ' . ($label !== '' ? 'امتحان «' . $label . '»' : 'الامتحان') . ': '
                  . $score
                  . ((int) $r['passed'] === 1 ? ' — اجتياز.' : ' — دون حدّ النجاح.');

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
     * «المحطة» في تقدّر هي `milestones`، واختبارها هو التقييم المرتبط بها —
     * إمّا بـ`assessments.milestone_id` وإمّا بـ`milestones.checkpoint_assessment_id`.
     * والوجهان مقروءان معًا لأن الجدولين يسمحان بالربط من أي طرف، والاكتفاء
     * بأحدهما يُسقط نصف الحالات صامتًا.
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
                               . ': المحاولة رقم ' . (int) $r['attempt_no'] . ' لم تبلغ حدّ النجاح. '
                               . 'المحطة لا تُفتح بالمحاولة بل بالإتقان، والإعادة متاحة بلا حدّ.',
            ));
        }

        $this->line('station_failed', count($rows), $written);
    }

    /**
     * شهادة جديدة.
     *
     * تعريف الشهادة هنا هو تعريف `tq_certificates.php` حرفًا بحرف: اجتياز
     * امتحان (`assessments.type = 'exam'` و`attempts.passed = 1`). ولو
     * اختلف التعريفان لظهرت للطالب شهادة لا يعرف بها وليّه، أو العكس.
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
                               . ' صارت متاحة، وتحمل رمز تحقّق يفتح صفحة عامّة تؤكّد صحّتها.',
            ));
        }

        $this->line('certificate', count($rows), $written);
    }

    /**
     * طلب حصّة خاصّة.
     *
     * `tutoring_sessions` بلا عمود زمن، فالمسح على الحالة `requested` وحدها
     * وذاكرة منع التكرار شهر: الطلب المعلّق لا يُعاد التنبيه به كل ليلة.
     * ويصل الإشعار المعلّمَ أيضًا — فهو الطرف المطلوب منه الردّ.
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
            $text = 'طُلبت حصّة خاصّة' . ($why !== '' ? ' بسبب هدف «' . $why . '»' : '') . '، وتنتظر ردّ المعلّم.';

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
                    'title'       => 'طلب حصّة خاصّة بانتظار ردّك',
                    'text'        => $text,
                )) > 0) ? 1 : 0;
            }
        }

        $this->line('session_request', count($rows), $written);
    }

    /* =====================================================================
       التقرير الأسبوعي
       ===================================================================== */

    /**
     * تقرير الأحد.
     *
     * الشاشة `tq_parent_weekly.php` تعرض التقرير ولا شيء كان يرسله. وهذا
     * ما يرسله — داخل المنصّة أولًا، والبريد خلف مفتاحه المُطفأ.
     *
     * ومصادر الأرقام هي مصادر الشاشة نفسها (`watch_histories`,
     * `quiz_results`)، فلا يقول الإشعار رقمًا تكذّبه الصفحة التي يفتحها.
     * وإشعار واحد لوليّ الأمر يجمع أبناءه لا إشعار لكل ابن: التقرير
     * الأسبوعي ملخّص، ولو تعدّد لصار قائمة.
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
                $now  = $this->active_days($sid, $week_start, PHP_INT_MAX);
                $was  = $this->active_days($sid, $prev_start, $week_start);
                $qz   = $this->quizzes_between($sid, $week_start, PHP_INT_MAX);

                $trend = ($now > $was) ? 'أفضل من الأسبوع الماضي'
                       : (($now < $was) ? 'أقلّ من الأسبوع الماضي' : 'كأسبوعه الماضي');

                // أرقام هندية وعربية في سطر واحد تُقرأ سطرين. الكلّ غربيّ هنا،
                // وعزلُها بصريًّا شأن الشاشة لا شأن نصّ مخزَّن.
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
     * محاولات الامتحانات المسلَّمة حديثًا.
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

    /** أيام درس فيها الطالب داخل مدّة — بمصادر التقرير الأسبوعي نفسها. */
    private function active_days($student_id, $from, $to)
    {
        $stamps = array();

        foreach ($this->db->query(
            'SELECT `date_updated` AS ts FROM `watch_histories` WHERE `student_id` = ?',
            array((int) $student_id)
        )->result_array() as $r) {
            $stamps[] = (int) $r['ts'];
        }

        foreach ($this->db->query(
            'SELECT `date_added` AS ts FROM `quiz_results` WHERE `user_id` = ? AND `is_submitted` = 1',
            array((int) $student_id)
        )->result_array() as $r) {
            $stamps[] = (int) $r['ts'];
        }

        $days = array();
        foreach ($stamps as $ts) {
            if ($ts <= 0 || $ts < $from || $ts >= $to) {
                continue;
            }
            $days[strtotime('today', $ts)] = true;
        }
        return count($days);
    }

    private function quizzes_between($student_id, $from, $to)
    {
        $row = $this->db->query(
            'SELECT COUNT(*) AS n FROM `quiz_results`
              WHERE `user_id` = ? AND `is_submitted` = 1
                AND CAST(`date_added` AS UNSIGNED) >= ?
                AND CAST(`date_added` AS UNSIGNED) < ?',
            array((int) $student_id, (int) $from, ($to === PHP_INT_MAX ? time() + 86400 : (int) $to))
        )->row_array();

        return (int) $row['n'];
    }

    /**
     * عدد الأيام بصيغة عربية سليمة.
     * «منذ 10 يومًا» خطأ ظاهر لوليّ الأمر: من ٣ إلى ١٠ جمعُ قلّة (أيام)،
     * وما بعدها تمييز مفرد منصوب (يومًا). والنصّ الذي يخطئ في هذا يُقرأ
     * على أنه آليّ فيُهمَل.
     */
    private function days_ar($n)
    {
        $n = (int) $n;
        if ($n === 1) return 'يوم واحد';
        if ($n === 2) return 'يومين';
        if ($n <= 10) return $n . ' أيام';
        return $n . ' يومًا';
    }

    /** عدد الاختبارات بصيغة عربية سليمة — لا «0 اختبارًا» ولا «2 اختبارًا». */
    private function quizzes_ar($n)
    {
        $n = (int) $n;
        if ($n === 0) return 'لم يُنهِ أي اختبار';
        if ($n === 1) return 'أنهى اختبارًا واحدًا';
        if ($n === 2) return 'أنهى اختبارين';
        if ($n <= 10) return 'أنهى ' . $n . ' اختبارات';
        return 'أنهى ' . $n . ' اختبارًا';
    }

    /** سطر واحد لكل حدث: المرشَّح والمكتوب — والفارق بينهما هو منع التكرار. */
    private function line($event, $candidates, $written)
    {
        echo date('Y-m-d H:i:s') . ' ' . $event
           . ' candidates=' . (int) $candidates
           . ' written=' . (int) $written . "\n";
    }
}
