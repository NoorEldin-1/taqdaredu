<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * محرك رحلة الطالب اليومية.
 *
 * ما يجمع هذه الأشياء الستة في ملف واحد أنها كلها تجيب سؤالا واحدا:
 * **ما الذي يفعله هذا الطالب الآن؟** — والتهيئة تجيبه أول مرة، والخطوة
 * التالية تجيبه كل يوم، والسلسلة تقول كم مرة أجاب، ووضع الامتحان يغير
 * الجواب كله لأسابيع، والملاحظات والنص هما ما يخلفه وراءه في الدرس.
 *
 * وأربعة جداول تنشأ وقت التشغيل لا بهجرة — كما `site_content` و
 * `payment_attempts` و`wallet_entries` قبلها، فالمستودع بلا هجرات:
 *
 *   tq_student_setup   صف لكل طالب: مواده وهدفه اليومي ووضع امتحانه
 *   tq_activity_day    صف لكل يوم درس فيه — وهو مصدر السلسلة الوحيد
 *   tq_lesson_note     ملاحظة موقوتة عند ثانية بعينها في درس
 *   tq_transcript      نص الدرس مقطعا مقطعا بثوانيه — للبحث وللقفز
 *
 * **والسلسلة تحسب ولا تخزن.** عمود `streak` كان سيكذب أول يوم ينقطع فيه
 * الطالب ولا ينادى فيه شيء يصفره. والحساب من `tq_activity_day` يعطي
 * الجواب الصحيح متى سئل، ولو لم يفتح أحد المنصة شهرا.
 */
class Taqdar_learn_model extends CI_Model
{
    /** وحدات الهدف اليومي — المفتاح يخزن، والتسمية تعرض. */
    private static $UNITS = array(
        'minutes' => array('label' => 'دقيقة',  'plural' => 'دقائق',   'default' => 30),
        'lessons' => array('label' => 'درس',    'plural' => 'دروس',    'default' => 2),
        'reviews' => array('label' => 'مراجعة', 'plural' => 'مراجعات', 'default' => 10),
    );

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /* =====================================================================
       المخطط
       ===================================================================== */

    public function ensure_schema()
    {
        static $done = false;
        if ($done) return;
        $done = true;

        $this->db->query(
            'CREATE TABLE IF NOT EXISTS `tq_student_setup` (
               `student_id`   INT(11)     NOT NULL,
               `subject_ids`  TEXT        NULL,
               `goal_unit`    VARCHAR(16) NOT NULL DEFAULT "minutes",
               `goal_value`   INT(11)     NOT NULL DEFAULT 30,
               `exam_from`    DATE        NULL,
               `exam_to`      DATE        NULL,
               `gamify`       TINYINT(1)  NOT NULL DEFAULT 1,
               `onboarded_at` DATETIME    NULL,
               `updated_at`   DATETIME    NULL,
               PRIMARY KEY (`student_id`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->db->query(
            'CREATE TABLE IF NOT EXISTS `tq_activity_day` (
               `id`         INT(11) NOT NULL AUTO_INCREMENT,
               `student_id` INT(11) NOT NULL,
               `day`        DATE    NOT NULL,
               `lessons`    INT(11) NOT NULL DEFAULT 0,
               `reviews`    INT(11) NOT NULL DEFAULT 0,
               `seconds`    INT(11) NOT NULL DEFAULT 0,
               `first_at`   DATETIME NULL,
               `last_at`    DATETIME NULL,
               PRIMARY KEY (`id`),
               UNIQUE KEY `uq_student_day` (`student_id`,`day`),
               KEY `ix_day` (`day`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->db->query(
            'CREATE TABLE IF NOT EXISTS `tq_lesson_note` (
               `id`         INT(11) NOT NULL AUTO_INCREMENT,
               `student_id` INT(11) NOT NULL,
               `lesson_id`  INT(11) NOT NULL,
               `at_second`  INT(11) NOT NULL DEFAULT 0,
               `body`       TEXT    NOT NULL,
               `created_at` DATETIME NULL,
               PRIMARY KEY (`id`),
               KEY `ix_owner` (`student_id`,`lesson_id`,`at_second`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->db->query(
            'CREATE TABLE IF NOT EXISTS `tq_transcript` (
               `id`        INT(11) NOT NULL AUTO_INCREMENT,
               `lesson_id` INT(11) NOT NULL,
               `at_second` INT(11) NOT NULL DEFAULT 0,
               `text`      TEXT    NOT NULL,
               PRIMARY KEY (`id`),
               KEY `ix_lesson` (`lesson_id`,`at_second`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    private function now() { return date('Y-m-d H:i:s'); }

    /** استعلام لا يسقط الشاشة إن كان الجدول لم ينشأ بعد. */
    private function safe($sql, $bind = array(), $default = array())
    {
        try {
            return $this->db->query($sql, $bind)->result_array();
        } catch (Throwable $e) {
            log_message('error', 'TQ-LEARN: ' . $e->getMessage());
            return $default;
        }
    }

    /* =====================================================================
       التهيئة — المرحلة والمواد والهدف اليومي
       ===================================================================== */

    public static function units() { return self::$UNITS; }

    /**
     * إعداد الطالب كاملا، بقيم افتراضية لمن لم يهيئ بعد.
     * لا يرجع `null` أبدا: كل قارئ هنا يريد أن يعرض شيئا، والفراغ يجبره
     * على تكرار الافتراضات في مكانه.
     */
    public function setup($student_id)
    {
        $this->ensure_schema();
        $student_id = (int) $student_id;

        $row = $this->db->where('student_id', $student_id)
                        ->get('tq_student_setup')->row_array();

        $unit = ($row && isset(self::$UNITS[$row['goal_unit']])) ? $row['goal_unit'] : 'minutes';

        return array(
            'student_id'   => $student_id,
            'subject_ids'  => $this->csv_ints($row ? $row['subject_ids'] : ''),
            'goal_unit'    => $unit,
            'goal_value'   => $row ? max(1, (int) $row['goal_value']) : self::$UNITS[$unit]['default'],
            'exam_from'    => $row ? $row['exam_from'] : null,
            'exam_to'      => $row ? $row['exam_to']   : null,
            'gamify'       => $row ? (int) $row['gamify'] : 1,
            'onboarded_at' => $row ? $row['onboarded_at'] : null,
            'done'         => (bool) ($row && $row['onboarded_at']),
        );
    }

    private function csv_ints($raw)
    {
        $out = array();
        foreach (explode(',', (string) $raw) as $p) {
            $p = (int) trim($p);
            if ($p > 0) $out[] = $p;
        }
        return array_values(array_unique($out));
    }

    /**
     * هل يحتاج هذا الطالب شاشة التهيئة؟
     *
     * مرة واحدة في العمر، وبعدها لا تعترضه أبدا — يعدل من الإعدادات إن
     * شاء. وشاشة تعود كل يوم تسأل السؤال نفسه ليست تهيئة بل ضريبة.
     */
    public function needs_setup($student_id)
    {
        $s = $this->setup($student_id);
        return !$s['done'];
    }

    /**
     * حفظ التهيئة.
     *
     * الصف يكتب في `users.grade_id` لأن هناك موضعه الأصلي — والكتالوج
     * والاختبار التشخيصي والباقات كلها تقرؤه من هناك. ولو خزن هنا ثانية
     * لصار موضعان لحقيقة واحدة يفترقان عند أول تعديل من اللوحة.
     */
    public function save_setup($student_id, $post)
    {
        $this->ensure_schema();
        $student_id = (int) $student_id;
        if ($student_id <= 0) return array('ok' => false, 'message' => 'لا حساب لهذا الطلب.');

        $grade_id = (int) (isset($post['grade_id']) ? $post['grade_id'] : 0);
        $subjects = isset($post['subject_ids']) ? $post['subject_ids'] : array();
        if (!is_array($subjects)) $subjects = explode(',', (string) $subjects);
        $subjects = $this->csv_ints(implode(',', $subjects));

        $unit = (string) (isset($post['goal_unit']) ? $post['goal_unit'] : 'minutes');
        if (!isset(self::$UNITS[$unit])) $unit = 'minutes';

        $value = (int) (isset($post['goal_value']) ? $post['goal_value'] : 0);
        if ($value < 1)   $value = self::$UNITS[$unit]['default'];
        if ($value > 600) $value = 600;

        if ($grade_id > 0) {
            $exists = $this->db->where('id', $grade_id)->count_all_results('grades');
            if ($exists) $this->db->where('id', $student_id)->update('users', array('grade_id' => $grade_id));
        }

        $now = $this->now();
        $this->db->query(
            'INSERT INTO `tq_student_setup`
                (`student_id`,`subject_ids`,`goal_unit`,`goal_value`,`onboarded_at`,`updated_at`)
             VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                `subject_ids` = VALUES(`subject_ids`),
                `goal_unit`   = VALUES(`goal_unit`),
                `goal_value`  = VALUES(`goal_value`),
                `onboarded_at`= COALESCE(`onboarded_at`, VALUES(`onboarded_at`)),
                `updated_at`  = VALUES(`updated_at`)',
            array($student_id, implode(',', $subjects), $unit, $value, $now, $now));

        return array('ok' => true, 'message' => 'حفظت خطتك. نبدأ من هنا.');
    }

    /** تبديل التلعيب — والوثيقة تشترط زر إيقاف كامل في الإعدادات. */
    public function set_gamify($student_id, $on)
    {
        $this->ensure_schema();
        $this->db->query(
            'INSERT INTO `tq_student_setup` (`student_id`,`gamify`,`updated_at`)
             VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE `gamify` = VALUES(`gamify`), `updated_at` = VALUES(`updated_at`)',
            array((int) $student_id, $on ? 1 : 0, $this->now()));
        return true;
    }

    /* =====================================================================
       المواد المتاحة — من المعروض لا من جدول التصنيف
       ===================================================================== */

    /**
     * مواد صف الطالب.
     *
     * تشتق من `paths` المنشورة لا من `subjects` كاملا — للسبب نفسه الذي
     * تشتق منه مرشحات الكتالوج من المعروض: في `paths` مواد بأرقام لا صف
     * لها في `subjects`، وقائمة مبنية من الجدول تسقطها. ومن لا صف له يرى
     * المواد كلها بدل شاشة فارغة.
     */
    public function subjects_for($student_id)
    {
        $grade_id = (int) $this->db->select('grade_id')->where('id', (int) $student_id)
                                   ->get('users')->row('grade_id');

        $sql = 'SELECT DISTINCT s.`id`, s.`name_ar`, COUNT(p.`id`) AS paths
                  FROM `paths` p
                  JOIN `subjects` s ON s.`id` = p.`subject_id`
                 WHERE p.`status` = "published"';
        $bind = array();
        if ($grade_id > 0) {
            $sql .= ' AND p.`grade_id` = ?';
            $bind[] = $grade_id;
        }
        $sql .= ' GROUP BY s.`id`, s.`name_ar` ORDER BY paths DESC, s.`name_ar` ASC';

        $rows = $this->safe($sql, $bind);

        // لا مسار منشور لصفه بعد: المواد كلها خير من لا شيء.
        if (!$rows) {
            $rows = $this->safe(
                'SELECT `id`, `name_ar`, 0 AS paths FROM `subjects` ORDER BY `name_ar` ASC');
        }

        foreach ($rows as &$r) {
            $r['id']    = (int) $r['id'];
            $r['paths'] = (int) $r['paths'];
        }
        unset($r);
        return $rows;
    }

    /* =====================================================================
       النشاط والسلسلة
       ===================================================================== */

    /**
     * يسجل نشاط اليوم. ينادى من كل موضع يعمل فيه الطالب شيئا حقيقيا:
     * حفظ مشاهدة، وإتقان درس، وإجابة مراجعة.
     *
     * والتسجيل مأمون التكرار في اليوم: `ON DUPLICATE KEY` يجمع ولا يكرر
     * صفا، و`first_at` تكتب مرة واحدة بـ`COALESCE`.
     */
    public function touch_activity($student_id, $kind = 'seconds', $amount = 0)
    {
        $this->ensure_schema();
        $student_id = (int) $student_id;
        if ($student_id <= 0) return;

        $col = in_array($kind, array('lessons', 'reviews', 'seconds'), true) ? $kind : 'seconds';
        $amount = max(0, (int) $amount);

        /* الصفر يسجل يوما ولا يزيد عدادا: من فتح درسا ولم يكمله درس اليوم
           في السلسلة. والسلسلة عن الحضور لا عن الإنجاز. */
        $now = $this->now();
        $this->db->query(
            'INSERT INTO `tq_activity_day` (`student_id`,`day`,`' . $col . '`,`first_at`,`last_at`)
             VALUES (?, CURDATE(), ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                `' . $col . '` = `' . $col . '` + VALUES(`' . $col . '`),
                `first_at`     = COALESCE(`first_at`, VALUES(`first_at`)),
                `last_at`      = VALUES(`last_at`)',
            array($student_id, $amount, $now, $now));
    }

    /**
     * السلسلة: كم يوما متتاليا حتى اليوم أو حتى أمس.
     *
     * **حتى أمس مقصود.** من درس أمس ولم يفتح المنصة اليوم بعد سلسلته
     * قائمة لم تنقطع — واليوم لم ينته. وتصفيرها الساعة الواحدة صباحا
     * عقاب على وقت لم يمض.
     */
    public function streak($student_id)
    {
        $this->ensure_schema();
        $student_id = (int) $student_id;

        $rows = $this->safe(
            'SELECT `day` FROM `tq_activity_day`
              WHERE `student_id` = ? AND `day` <= CURDATE()
              ORDER BY `day` DESC LIMIT 400', array($student_id));

        if (!$rows) {
            return array('days' => 0, 'today' => false, 'best' => 0, 'has_source' => true);
        }

        $days = array();
        foreach ($rows as $r) $days[$r['day']] = true;

        $today     = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $has_today = isset($days[$today]);

        // نقطة البدء: اليوم إن درس فيه، وإلا أمس — وإن لم يدرس فيهما فلا سلسلة.
        $cursor = $has_today ? $today : ($yesterday && isset($days[$yesterday]) ? $yesterday : null);

        $run = 0;
        while ($cursor !== null && isset($days[$cursor])) {
            $run++;
            $cursor = date('Y-m-d', strtotime($cursor . ' -1 day'));
        }

        // الأطول: مرور واحد على الأيام مرتبة تصاعدا.
        $sorted = array_keys($days);
        sort($sorted);
        $best = 0; $cur = 0; $prev = null;
        foreach ($sorted as $d) {
            $cur  = ($prev !== null && $d === date('Y-m-d', strtotime($prev . ' +1 day'))) ? $cur + 1 : 1;
            $best = max($best, $cur);
            $prev = $d;
        }

        return array('days' => $run, 'today' => $has_today, 'best' => $best, 'has_source' => true);
    }

    /**
     * تقدم اليوم مقابل الهدف — الحلقة التي ترسم في اللوحة.
     * تعيد النسبة مقصوصة عند مئة، والفائض يقال رقما لا تجاوزا للحلقة.
     */
    public function goal_today($student_id)
    {
        $this->ensure_schema();
        $setup = $this->setup($student_id);
        $unit  = $setup['goal_unit'];
        $target = max(1, (int) $setup['goal_value']);

        $row = $this->db->where('student_id', (int) $student_id)
                        ->where('day', date('Y-m-d'))
                        ->get('tq_activity_day')->row_array();

        $done = 0;
        if ($row) {
            if ($unit === 'minutes')      $done = (int) floor((int) $row['seconds'] / 60);
            elseif ($unit === 'lessons')  $done = (int) $row['lessons'];
            else                          $done = (int) $row['reviews'];
        }

        return array(
            'unit'    => $unit,
            'label'   => self::$UNITS[$unit]['label'],
            'plural'  => self::$UNITS[$unit]['plural'],
            'target'  => $target,
            'done'    => $done,
            'percent' => (int) min(100, round($done * 100 / $target)),
            'met'     => $done >= $target,
            'gamify'  => (int) $setup['gamify'] === 1,
        );
    }

    /** أيام النشاط في مدى — لخريطة الأسابيع في اللوحة والتقارير. */
    public function activity_range($student_id, $days = 28)
    {
        $this->ensure_schema();
        $days = max(1, min(370, (int) $days));

        $rows = $this->safe(
            'SELECT `day`, `lessons`, `reviews`, `seconds`
               FROM `tq_activity_day`
              WHERE `student_id` = ? AND `day` > DATE_SUB(CURDATE(), INTERVAL ? DAY)
              ORDER BY `day` ASC', array((int) $student_id, $days));

        $map = array();
        foreach ($rows as $r) {
            $map[$r['day']] = array(
                'lessons' => (int) $r['lessons'],
                'reviews' => (int) $r['reviews'],
                'seconds' => (int) $r['seconds'],
            );
        }

        $out = array();
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime('-' . $i . ' day'));
            $out[] = array('day' => $d) + (isset($map[$d])
                ? $map[$d] + array('active' => true)
                : array('lessons' => 0, 'reviews' => 0, 'seconds' => 0, 'active' => false));
        }
        return $out;
    }

    /* =====================================================================
       وضع الامتحان
       ===================================================================== */

    /**
     * حال وضع الامتحان.
     *
     * مدى تاريخين يضبطه الطالب. وما دام ساريا: الواجهة تحول إلى خطة
     * مراجعة، والإشعارات التسويقية تتوقف — والقرار الثاني في
     * `Taqdar_events_model::notify()` يقرأ من هنا، فمصدر الحكم واحد.
     */
    public function exam_mode($student_id)
    {
        $this->ensure_schema();
        $s = $this->setup($student_id);

        $from = $s['exam_from'];
        $to   = $s['exam_to'];
        if (!$from || !$to) {
            return array('active' => false, 'from' => null, 'to' => null, 'days_left' => 0);
        }

        $today  = date('Y-m-d');
        $active = ($today >= $from && $today <= $to);
        $left   = $active ? (int) floor((strtotime($to) - strtotime($today)) / 86400) + 1 : 0;

        return array(
            'active'    => $active,
            'from'      => $from,
            'to'        => $to,
            'days_left' => max(0, $left),
            'upcoming'  => (!$active && $today < $from),
        );
    }

    public function set_exam_mode($student_id, $from, $to)
    {
        $this->ensure_schema();
        $student_id = (int) $student_id;

        // الإلغاء: تاريخ فارغ يمحو المدى ولا يترك نصفه.
        if (!$from || !$to) {
            $this->db->query(
                'INSERT INTO `tq_student_setup` (`student_id`,`exam_from`,`exam_to`,`updated_at`)
                 VALUES (?,NULL,NULL,?)
                 ON DUPLICATE KEY UPDATE `exam_from` = NULL, `exam_to` = NULL,
                                         `updated_at` = VALUES(`updated_at`)',
                array($student_id, $this->now()));
            return array('ok' => true, 'message' => 'أوقف وضع الامتحان، وعادت شاشاتك كما كانت.');
        }

        $f = date('Y-m-d', strtotime($from));
        $t = date('Y-m-d', strtotime($to));
        if ($t < $f) { $tmp = $f; $f = $t; $t = $tmp; }

        // ثلاثة أشهر سقفا: مدى أطول ليس وضع امتحان بل إطفاء دائم للمنصة.
        if ((strtotime($t) - strtotime($f)) > 92 * 86400) {
            return array('ok' => false, 'message' => 'أطول مدى لوضع الامتحان ثلاثة أشهر.');
        }

        $this->db->query(
            'INSERT INTO `tq_student_setup` (`student_id`,`exam_from`,`exam_to`,`updated_at`)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE `exam_from` = VALUES(`exam_from`),
                                     `exam_to`   = VALUES(`exam_to`),
                                     `updated_at`= VALUES(`updated_at`)',
            array($student_id, $f, $t, $this->now()));

        return array('ok' => true,
                     'message' => 'فعل وضع الامتحان حتى ' . $t . '. شاشاتك الآن خطة مراجعة، والإشعارات التسويقية موقوفة.');
    }

    /* =====================================================================
       الخطوة التالية — قرار واحد لا قائمة
       ===================================================================== */

    /**
     * الخطوة الواحدة المقترحة الآن.
     *
     * `B1.7` يشترط «خطوة واحدة مقترحة لا قائمة»، و`F1.4` «زر واحد كبير».
     * والفرق بين هذا و«واصل من حيث وقفت» أن هذا **يوازن**: مراجعة مستحقة
     * اليوم أهم من درس جديد، لأن ما نسي لا يعوض بما يضاف. وواجب مسلم
     * موعده أقرب من كليهما.
     *
     * والترتيب ثابت ومكتوب، فلا يفسر أحد نتيجة غير التي يقصدها:
     *   1. لم يهيئ حسابه بعد          → التهيئة
     *   2. وضع الامتحان سار           → خطة المراجعة، والمراجعة أولا دائما
     *   3. مراجعة مستحقة              → مراجعة اليوم
     *   4. واجب لم يسلم               → مهامي
     *   5. درس بدأه ولم يكمله         → أكمله من موضعه
     *   6. درس تال مفتوح              → ابدأه
     *   7. لا محتوى                   → الكتالوج
     */
    public function next_step($student_id)
    {
        $this->ensure_schema();
        $student_id = (int) $student_id;

        if ($this->needs_setup($student_id)) {
            return $this->step('setup', 'اضبط خطتك أولا',
                'مرحلتك وموادك وهدفك اليومي — دقيقة واحدة، ثم نبدأ.',
                'student/setup', 'ابدأ الإعداد', 'target');
        }

        $exam = $this->exam_mode($student_id);

        $due = 0;
        try {
            $this->load->model('taqdar_repo_model', 'tq_repo');
            $due = (int) $this->tq_repo->count_due_reviews($student_id);
        } catch (Throwable $e) {
            $due = 0;
        }

        if ($due > 0) {
            $sub = $exam['active']
                ? 'وضع الامتحان سار، والمراجعة أول ما يثبت قبله.'
                : 'أسئلة أتقنتها من قبل عادت اليوم لتثبت. لا تتجاوز دفعة واحدة.';
            return $this->step('review',
                'راجع ' . $this->count_ar($due, 'سؤالا', 'سؤالين', 'أسئلة') . ' اليوم',
                $sub, 'student/reviews', 'ابدأ المراجعة', 'flame',
                array('due' => $due, 'exam_mode' => $exam['active']));
        }

        // وضع الامتحان بلا مستحق: خطة المراجعة نفسها هي الوجهة، لا درس جديد.
        if ($exam['active']) {
            return $this->step('exam_plan', 'خطة مراجعتك للامتحان',
                'بقي ' . $this->count_ar($exam['days_left'], 'يوم', 'يومان', 'أيام')
                . '. راجع أضعف أهدافك بدل أن تبدأ جديدا.',
                'student/exams', 'افتح الخطة', 'check-badge',
                array('days_left' => $exam['days_left']));
        }

        $task = $this->pending_task($student_id);
        if ($task) {
            return $this->step('task', 'سلم واجب ' . $task['lesson_title'],
                'واجب مسند إليك في ' . $task['course_title'] . ' لم يسلم بعد.',
                'student/tasks', 'افتح الواجب', 'clipboard',
                array('lesson_id' => (int) $task['lesson_id']));
        }

        $resume = $this->resume_lesson($student_id);
        if ($resume) {
            return $this->step('resume', 'أكمل ' . $resume['title'],
                'وقفت عند الدقيقة ' . $this->mmss((int) $resume['position_sec']) . '.',
                'student/lesson/' . (int) $resume['course_id'] . '/' . (int) $resume['lesson_id'],
                'أكمل الدرس', 'play',
                array('lesson_id' => (int) $resume['lesson_id'],
                      'position_sec' => (int) $resume['position_sec']));
        }

        $next = $this->next_open_lesson($student_id);
        if ($next) {
            return $this->step('lesson', 'ابدأ ' . $next['title'],
                'درسك التالي في ' . $next['course_title'] . '.',
                'student/lesson/' . (int) $next['course_id'] . '/' . (int) $next['lesson_id'],
                'ابدأ الدرس', 'play',
                array('lesson_id' => (int) $next['lesson_id']));
        }

        return $this->step('browse', 'اختر ما تتعلمه',
            'لا درس مفتوح أمامك الآن. تصفح البرامج واختر ما يناسب مرحلتك.',
            'catalog', 'تصفح الكتالوج', 'grid');
    }

    private function step($kind, $title, $sub, $path, $cta, $icon, $meta = array())
    {
        return array(
            'kind' => $kind, 'title' => $title, 'subtitle' => $sub,
            'href' => base_url($path), 'cta' => $cta, 'icon' => $icon,
            'meta' => $meta,
        );
    }

    /** أقرب واجب لم تسلم محاولته — من الجداول نفسها التي تقرأ منها `tq_tasks`. */
    private function pending_task($student_id)
    {
        $rows = $this->safe(
            'SELECT a.`id`, l.`id` AS lesson_id, l.`title` AS lesson_title,
                    c.`id` AS course_id, c.`title` AS course_title
               FROM `assessments` a
               JOIN `lesson` l ON l.`id` = a.`lesson_id`
               JOIN `course` c ON c.`id` = l.`course_id`
               JOIN `enrol`  e ON e.`course_id` = c.`id` AND e.`user_id` = ?
              WHERE a.`type` = "homework"
                AND NOT EXISTS (SELECT 1 FROM `attempts` t
                                 WHERE t.`assessment_id` = a.`id` AND t.`student_id` = ?
                                   AND t.`submitted_at` IS NOT NULL)
              ORDER BY a.`id` ASC LIMIT 1',
            array($student_id, $student_id));

        return $rows ? $rows[0] : null;
    }

    /** درس بدأه ولم يكمله — الأحدث لمسا. */
    private function resume_lesson($student_id)
    {
        $rows = $this->safe(
            'SELECT lp.`lesson_id`, lp.`position_sec`, l.`title`, l.`course_id`
               FROM `lesson_progress` lp
               JOIN `lesson` l ON l.`id` = lp.`lesson_id`
              WHERE lp.`student_id` = ? AND lp.`position_sec` > 15
                AND lp.`completed_at` IS NULL
              ORDER BY lp.`id` DESC LIMIT 1', array($student_id));

        return $rows ? $rows[0] : null;
    }

    /**
     * أول درس مفتوح لم يبدأ في كورس مسجل.
     * القفل يسأل عنه `Taqdar_repo_model` — مصدر القرار الوحيد — فلا تعاد
     * قواعده هنا بصيغة ثانية تفترق عنه عند أول تعديل.
     */
    private function next_open_lesson($student_id)
    {
        $rows = $this->safe(
            'SELECT l.`id` AS lesson_id, l.`title`, l.`course_id`, c.`title` AS course_title
               FROM `enrol` e
               JOIN `course` c ON c.`id` = e.`course_id`
               JOIN `lesson` l ON l.`course_id` = c.`id`
              WHERE e.`user_id` = ?
                AND NOT EXISTS (SELECT 1 FROM `lesson_progress` p
                                 WHERE p.`lesson_id` = l.`id` AND p.`student_id` = ?
                                   AND p.`completed_at` IS NOT NULL)
              ORDER BY c.`id` ASC, l.`order` ASC, l.`id` ASC LIMIT 40',
            array($student_id, $student_id));

        if (!$rows) return null;

        try {
            $this->load->model('taqdar_repo_model', 'tq_repo');
            foreach ($rows as $r) {
                if ($this->tq_repo->is_lesson_unlocked($student_id, (int) $r['lesson_id'])) return $r;
            }
        } catch (Throwable $e) {
            return $rows[0];
        }
        return null;
    }

    private function mmss($sec)
    {
        $sec = max(0, (int) $sec);
        return sprintf('%d:%02d', intdiv($sec, 60), $sec % 60);
    }

    /** تمييز العدد بالعربية — أربع حالات لا حالتان. */
    private function count_ar($n, $one, $two, $many)
    {
        $n = (int) $n;
        if ($n === 1) return $one;
        if ($n === 2) return $two;
        if ($n >= 3 && $n <= 10) return $n . ' ' . $many;
        return $n . ' ' . $one;
    }

    /* =====================================================================
       الملاحظات الموقوتة
       ===================================================================== */

    public function notes($student_id, $lesson_id)
    {
        $this->ensure_schema();
        $rows = $this->safe(
            'SELECT `id`,`at_second`,`body`,`created_at` FROM `tq_lesson_note`
              WHERE `student_id` = ? AND `lesson_id` = ?
              ORDER BY `at_second` ASC, `id` ASC',
            array((int) $student_id, (int) $lesson_id));

        foreach ($rows as &$r) {
            $r['id']        = (int) $r['id'];
            $r['at_second'] = (int) $r['at_second'];
            $r['at_label']  = $this->mmss($r['at_second']);
        }
        unset($r);
        return $rows;
    }

    public function add_note($student_id, $lesson_id, $at_second, $body)
    {
        $this->ensure_schema();
        $body = trim((string) $body);
        if ($body === '') return array('ok' => false, 'message' => 'اكتب الملاحظة قبل الحفظ.');
        if (mb_strlen($body) > 2000) $body = mb_substr($body, 0, 2000);

        $this->db->insert('tq_lesson_note', array(
            'student_id' => (int) $student_id,
            'lesson_id'  => (int) $lesson_id,
            'at_second'  => max(0, (int) $at_second),
            'body'       => $body,
            'created_at' => $this->now(),
        ));

        return array('ok' => true, 'id' => (int) $this->db->insert_id(),
                     'message' => 'حفظت الملاحظة عند ' . $this->mmss($at_second) . '.');
    }

    /** الحذف يشترط الملكية في الاستعلام نفسه لا في شرط قبله. */
    public function delete_note($student_id, $note_id)
    {
        $this->ensure_schema();
        $this->db->where('id', (int) $note_id)->where('student_id', (int) $student_id)
                 ->delete('tq_lesson_note');
        return array('ok' => (bool) $this->db->affected_rows(),
                     'message' => $this->db->affected_rows() ? 'حذفت الملاحظة.' : 'لا ملاحظة بهذا الرقم في حسابك.');
    }

    /* =====================================================================
       نص الدرس — للبحث وللقفز
       ===================================================================== */

    public function transcript($lesson_id)
    {
        $this->ensure_schema();
        $rows = $this->safe(
            'SELECT `at_second`,`text` FROM `tq_transcript`
              WHERE `lesson_id` = ? ORDER BY `at_second` ASC', array((int) $lesson_id));

        foreach ($rows as &$r) {
            $r['at_second'] = (int) $r['at_second'];
            $r['at_label']  = $this->mmss($r['at_second']);
        }
        unset($r);
        return $rows;
    }

    public function has_transcript($lesson_id)
    {
        $this->ensure_schema();
        try {
            return (int) $this->db->where('lesson_id', (int) $lesson_id)
                                  ->count_all_results('tq_transcript') > 0;
        } catch (Throwable $e) { return false; }
    }

    /**
     * يكتب نص درس كاملا. الصيغة سطر لكل مقطع: `mm:ss نص` أو `ثوان نص`.
     * ويستبدل ما كان — النص وثيقة واحدة لا تراكم أسطر من رفعين.
     */
    public function save_transcript($lesson_id, $raw)
    {
        $this->ensure_schema();
        $lesson_id = (int) $lesson_id;
        $lines = preg_split('/\r\n|\r|\n/', (string) $raw);

        $rows = array();
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            if (preg_match('/^(?:\[)?(\d{1,2}):(\d{2})(?::(\d{2}))?(?:\])?\s*[-–—]?\s*(.+)$/u', $line, $m)) {
                $sec = isset($m[3]) && $m[3] !== ''
                    ? ((int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3])
                    : ((int) $m[1] * 60 + (int) $m[2]);
                $rows[] = array('lesson_id' => $lesson_id, 'at_second' => $sec, 'text' => trim($m[4]));
            } elseif (preg_match('/^(\d{1,6})\s+(.+)$/u', $line, $m)) {
                $rows[] = array('lesson_id' => $lesson_id, 'at_second' => (int) $m[1], 'text' => trim($m[2]));
            } else {
                // سطر بلا ختم زمني يلحق بالمقطع قبله بدل أن يسقط صامتا.
                if ($rows) $rows[count($rows) - 1]['text'] .= ' ' . $line;
                else       $rows[] = array('lesson_id' => $lesson_id, 'at_second' => 0, 'text' => $line);
            }
        }

        $this->db->where('lesson_id', $lesson_id)->delete('tq_transcript');
        if ($rows) $this->db->insert_batch('tq_transcript', $rows);

        return array('ok' => true, 'count' => count($rows),
                     'message' => 'حفظ النص في ' . count($rows) . ' مقطعا.');
    }
}
