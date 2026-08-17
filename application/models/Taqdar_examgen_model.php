<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * مولد الاختبارات — نسخ متكافئة لا مختلفة.
 *
 * `B2.9` معيار قبوله: «نفس الوصفة تنتج اختبارات مختلفة الترتيب لكل
 * طالب». و**متكافئة** هي الكلمة التي تحمل المعنى كله: النسخ تختلف في
 * الترتيب وتتطابق في القياس. فلو اختلفت في الأسئلة نفسها لصار الطالبان
 * يقاسان بمسطرتين، ودرجتاهما لا تقارنان — وذلك عيب لا ميزة.
 *
 * فالوصفة (`blueprint`) تقول: **أي الأهداف** ومنه **كم سؤالا**. وكل
 * النسخ تسحب من الوصفة نفسها فتغطي الأهداف نفسها بالأعداد نفسها، ثم
 * تختلف في شيئين لا ثالث لهما:
 *
 *   ١ — ترتيب الأسئلة
 *   ٢ — ترتيب الخيارات داخل كل سؤال
 *
 * وكلاهما لا يمس الصعوبة، ويمنع النقل بالنظر إلى شاشة الجار.
 *
 * **والخلط مبذور لا عشوائي.** بذرة النسخة ثابتة (`assessment:form`)،
 * فالنسخة «ب» هي هي في كل مرة تقرأ: من فتح اختباره ثم انقطع اتصاله
 * يعود إلى الترتيب نفسه لا إلى ترتيب جديد. والعشوائية غير المبذورة
 * تجعل «أعد التحميل» بابا لاختبار آخر.
 *
 * **والإجابات الصحيحة لا تخرج من هنا أبدا.** تخرج النسخة بترتيب خياراتها
 * وحده، والتصحيح في `Taqdar_repo_model::is_answer_correct()` على النص
 * لا على الموضع — فخلط الخيارات لا يكسر التصحيح ولا يحتاج خريطة عودة.
 */
class Taqdar_examgen_model extends CI_Model
{
    /** رموز النسخ. أربع تكفي قاعة: الخامسة لا تزيد منعا وتزيد عبئا. */
    private static $FORMS = array('أ', 'ب', 'ج', 'د');

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    public static function forms() { return self::$FORMS; }

    /* =====================================================================
       المخطط
       ===================================================================== */

    public function ensure_schema()
    {
        static $done = false;
        if ($done) return;
        $done = true;

        $this->db->query(
            'CREATE TABLE IF NOT EXISTS `tq_exam_blueprint` (
               `id`            INT(11)      NOT NULL AUTO_INCREMENT,
               `assessment_id` INT(11)      NOT NULL,
               `title`         VARCHAR(190) NULL,
               `spec`          TEXT         NULL,
               `forms`         TINYINT(2)   NOT NULL DEFAULT 4,
               `created_by`    INT(11)      NULL,
               `created_at`    DATETIME     NULL,
               `updated_at`    DATETIME     NULL,
               PRIMARY KEY (`id`),
               UNIQUE KEY `uq_assessment` (`assessment_id`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->db->query(
            'CREATE TABLE IF NOT EXISTS `tq_exam_form` (
               `id`            INT(11)     NOT NULL AUTO_INCREMENT,
               `assessment_id` INT(11)     NOT NULL,
               `form_key`      VARCHAR(4)  NOT NULL,
               `question_ids`  TEXT        NULL,
               `seed`          VARCHAR(40) NOT NULL,
               `created_at`    DATETIME    NULL,
               PRIMARY KEY (`id`),
               UNIQUE KEY `uq_form` (`assessment_id`,`form_key`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->db->query(
            'CREATE TABLE IF NOT EXISTS `tq_exam_assign` (
               `assessment_id` INT(11)    NOT NULL,
               `student_id`    INT(11)    NOT NULL,
               `form_key`      VARCHAR(4) NOT NULL,
               `assigned_at`   DATETIME   NULL,
               PRIMARY KEY (`assessment_id`,`student_id`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    private function now() { return date('Y-m-d H:i:s'); }

    private function safe($sql, $bind = array())
    {
        try { return $this->db->query($sql, $bind)->result_array(); }
        catch (Throwable $e) { log_message('error', 'TQ-EXAMGEN: ' . $e->getMessage()); return array(); }
    }

    /* =====================================================================
       الوصفة
       ===================================================================== */

    public function blueprint($assessment_id)
    {
        $this->ensure_schema();
        $row = $this->db->where('assessment_id', (int) $assessment_id)
                        ->get('tq_exam_blueprint')->row_array();
        if (!$row) return null;

        $row['spec'] = $row['spec'] ? json_decode($row['spec'], true) : array();
        return $row;
    }

    /**
     * أهداف متاحة لاختبار، ومعها عدد أسئلة كل هدف.
     *
     * تشتق من **الأسئلة الموجودة فعلا** لا من جدول الأهداف: هدف بلا
     * سؤال لا يوضع في وصفة، ووصفة تطلب منه ثلاثة تنتج نسخا ناقصة بلا
     * خطأ ظاهر — وذلك أسوأ أنواع العطل.
     */
    public function available_objectives($assessment_id)
    {
        $this->ensure_schema();
        $a = $this->db->where('id', (int) $assessment_id)->get('assessments')->row_array();
        if (!$a) return array();

        /* نطاق الأسئلة يتبع نوع التقييم: تقييم درس يسحب من أهداف درسه،
           وتقييم محطة من أهداف دروسها كلها. */
        if (!empty($a['lesson_id'])) {
            $where = 'o.`lesson_id` = ?';
            $bind  = array((int) $a['lesson_id']);
        } elseif (!empty($a['milestone_id'])) {
            $where = 'l.`milestone_id` = ?';
            $bind  = array((int) $a['milestone_id']);
        } elseif (!empty($a['path_id'])) {
            $where = 'l.`course_id` IN (SELECT `course_id` FROM `paths` WHERE `id` = ?)';
            $bind  = array((int) $a['path_id']);
        } else {
            return array();
        }

        return $this->safe(
            'SELECT o.`id`, o.`text`, o.`at_second`, o.`lesson_id`,
                    l.`title` AS lesson_title, COUNT(q.`id`) AS bank
               FROM `objectives` o
               JOIN `lesson` l ON l.`id` = o.`lesson_id`
               JOIN `question` q ON q.`objective_id` = o.`id`
              WHERE ' . $where . '
              GROUP BY o.`id`, o.`text`, o.`at_second`, o.`lesson_id`, l.`title`
             HAVING bank > 0
              ORDER BY l.`id` ASC, o.`at_second` ASC', $bind);
    }

    /**
     * يحفظ الوصفة.
     * @param array $spec [objective_id => عدد الأسئلة]
     */
    public function save_blueprint($assessment_id, $spec, $forms = 4, $actor_id = null)
    {
        $this->ensure_schema();
        $assessment_id = (int) $assessment_id;

        $clean = array();
        foreach ((array) $spec as $oid => $n) {
            $oid = (int) $oid; $n = (int) $n;
            if ($oid > 0 && $n > 0) $clean[$oid] = min(20, $n);
        }
        if (!$clean) {
            return array('ok' => false, 'message' => 'حدد هدفا واحدا على الأقل وعدد أسئلته.');
        }

        /* البنك يفحص قبل الحفظ لا عند التوليد: وصفة تطلب خمسة من هدف
           فيه ثلاثة تحفظ ثم تنتج نسخا ناقصة، ولا يعلم صاحبها إلا حين
           يقرأ طالب اختبارا أقصر مما وعد. */
        $bank = array();
        foreach ($this->available_objectives($assessment_id) as $o) $bank[(int) $o['id']] = (int) $o['bank'];

        foreach ($clean as $oid => $n) {
            $have = isset($bank[$oid]) ? $bank[$oid] : 0;
            if ($have < $n) {
                return array('ok' => false,
                    'message' => 'الهدف رقم ' . $oid . ' فيه ' . $have . ' سؤالا وطلبت ' . $n
                               . '. أضف أسئلة إلى البنك أو أنقص العدد.');
            }
        }

        $forms = max(1, min(count(self::$FORMS), (int) $forms));
        $now   = $this->now();

        $this->db->query(
            'INSERT INTO `tq_exam_blueprint`
                (`assessment_id`,`spec`,`forms`,`created_by`,`created_at`,`updated_at`)
             VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE `spec` = VALUES(`spec`), `forms` = VALUES(`forms`),
                                     `updated_at` = VALUES(`updated_at`)',
            array($assessment_id, json_encode($clean), $forms,
                  $actor_id ? (int) $actor_id : null, $now, $now));

        $total = array_sum($clean);
        return array('ok' => true, 'total' => $total,
                     'message' => 'حفظت الوصفة: ' . $total . ' سؤالا من ' . count($clean)
                                . ' هدفا، في ' . $forms . ' نسخا متكافئة.');
    }

    /* =====================================================================
       التوليد
       ===================================================================== */

    /**
     * يولد النسخ من الوصفة.
     *
     * كل نسخة تسحب **الأسئلة نفسها** — أول `n` من كل هدف بترتيب ثابت —
     * ثم تخلط ترتيبها ببذرتها. فالتكافؤ مضمون بالبناء لا بالحظ: النسخ
     * الأربع مجموعات متطابقة العناصر، مختلفة الترتيب وحده.
     */
    public function generate($assessment_id, $actor_id = null)
    {
        $this->ensure_schema();
        $assessment_id = (int) $assessment_id;

        $bp = $this->blueprint($assessment_id);
        if (!$bp || !$bp['spec']) {
            return array('ok' => false, 'message' => 'لا وصفة لهذا الاختبار بعد. احفظ الوصفة أولا.');
        }

        $picked = array();
        foreach ($bp['spec'] as $oid => $n) {
            $rows = $this->safe(
                'SELECT `id` FROM `question` WHERE `objective_id` = ?
                  ORDER BY `order` ASC, `id` ASC LIMIT ' . (int) $n, array((int) $oid));
            foreach ($rows as $r) $picked[] = (int) $r['id'];
        }

        if (!$picked) {
            return array('ok' => false, 'message' => 'لا أسئلة في البنك تطابق الوصفة.');
        }

        $forms = max(1, min(count(self::$FORMS), (int) $bp['forms']));
        $now   = $this->now();
        $made  = 0;

        for ($i = 0; $i < $forms; $i++) {
            $key  = self::$FORMS[$i];
            $seed = 'a' . $assessment_id . ':' . $key;
            $ids  = $picked;
            $this->seeded_shuffle($ids, $seed);

            $this->db->query(
                'INSERT INTO `tq_exam_form`
                    (`assessment_id`,`form_key`,`question_ids`,`seed`,`created_at`)
                 VALUES (?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE `question_ids` = VALUES(`question_ids`),
                                         `seed` = VALUES(`seed`)',
                array($assessment_id, $key, implode(',', $ids), $seed, $now));
            $made++;
        }

        /* الإسنادات القديمة تمحى: النسخ تغيرت، ومن أسند إلى «ب» قديمة
           يسند إلى «ب» جديدة عند أول فتح. ومن سلم فعلا لا يمسه هذا —
           إجابته محفوظة في `answers` بمعرف السؤال لا بموضعه. */
        $this->db->where('assessment_id', $assessment_id)->delete('tq_exam_assign');

        try {
            $this->load->model('taqdar_repo_model', 'tq_repo');
            $this->tq_repo->audit($actor_id, 'examgen.generate', 'assessments:' . $assessment_id,
                                  null, array('forms' => $made, 'questions' => count($picked)));
        } catch (Throwable $e) {}

        return array('ok' => true, 'forms' => $made, 'questions' => count($picked),
                     'message' => 'ولد ' . $made . ' نسخا متكافئة، في كل واحدة '
                                . count($picked) . ' سؤالا بترتيب مختلف.');
    }

    /**
     * خلط مبذور — `Fisher-Yates` بمولد خطي من البذرة.
     *
     * ولا `shuffle()` ولا `srand()`: الأولى غير مبذورة، والثانية تعبث
     * بالحالة العامة للعشوائية في الطلب كله — فتغير بعدها كل نداء
     * `rand()` في الشيفرة، ومنها ما يولد رموز تحقق.
     */
    private function seeded_shuffle(&$arr, $seed)
    {
        $n = count($arr);
        if ($n < 2) return;

        $s = crc32($seed);
        for ($i = $n - 1; $i > 0; $i--) {
            $s = ($s * 1103515245 + 12345) & 0x7FFFFFFF;
            $j = $s % ($i + 1);
            $t = $arr[$i]; $arr[$i] = $arr[$j]; $arr[$j] = $t;
        }
    }

    public function forms_of($assessment_id)
    {
        $this->ensure_schema();
        $rows = $this->safe(
            'SELECT `form_key`,`question_ids`,`seed` FROM `tq_exam_form`
              WHERE `assessment_id` = ? ORDER BY `id` ASC', array((int) $assessment_id));

        foreach ($rows as &$r) {
            $r['count'] = $r['question_ids'] ? count(explode(',', $r['question_ids'])) : 0;
        }
        unset($r);
        return $rows;
    }

    /* =====================================================================
       الإسناد والقراءة
       ===================================================================== */

    /**
     * نسخة هذا الطالب. تسند مرة وتثبت: من فتح «ج» يرى «ج» في كل مرة،
     * وإعادة التحميل لا تعطيه اختبارا آخر.
     *
     * والتوزيع بباقي القسمة على معرف الطالب لا بعشوائية: يوزع بالتساوي،
     * ولا يعطي جارين في القاعة النسخة نفسها بالضرورة — والمعرفان
     * متتاليان لمن سجلا معا لا لمن جلسا معا.
     */
    public function form_for($assessment_id, $student_id)
    {
        $this->ensure_schema();
        $assessment_id = (int) $assessment_id;
        $student_id    = (int) $student_id;

        $row = $this->db->where('assessment_id', $assessment_id)->where('student_id', $student_id)
                        ->get('tq_exam_assign')->row_array();
        if ($row) return $row['form_key'];

        $forms = $this->forms_of($assessment_id);
        if (!$forms) return null;

        $key = $forms[$student_id % count($forms)]['form_key'];

        $this->db->query(
            'INSERT INTO `tq_exam_assign` (`assessment_id`,`student_id`,`form_key`,`assigned_at`)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE `form_key` = `form_key`',
            array($assessment_id, $student_id, $key, $this->now()));

        return $key;
    }

    /**
     * أسئلة نسخة الطالب — **بلا مفاتيح حل**، وبخيارات مخلوطة ببذرته.
     *
     * وبذرة الخيارات تشمل معرف الطالب: نسختان لطالبين في نسخة واحدة
     * تختلفان في ترتيب الخيارات كذلك، فلا ينقل جار عن جار «الخيار
     * الثالث» ولو تشابهت نسختاهما.
     */
    public function questions_for($assessment_id, $student_id)
    {
        $this->ensure_schema();
        $key = $this->form_for($assessment_id, $student_id);
        if (!$key) return array();

        $form = $this->db->where('assessment_id', (int) $assessment_id)
                         ->where('form_key', $key)->get('tq_exam_form')->row_array();
        if (!$form || !$form['question_ids']) return array();

        $ids = array_map('intval', explode(',', $form['question_ids']));
        $ids = array_values(array_filter($ids));
        if (!$ids) return array();

        $rows = $this->safe(
            'SELECT q.`id`, q.`title`, q.`type`, q.`number_of_options`, q.`options`,
                    q.`objective_id`, o.`text` AS objective_text
               FROM `question` q
          LEFT JOIN `objectives` o ON o.`id` = q.`objective_id`
              WHERE q.`id` IN (' . implode(',', $ids) . ')');

        // ترتيب النسخة يعلو ترتيب القاعدة
        $by = array();
        foreach ($rows as $r) $by[(int) $r['id']] = $r;

        $out = array();
        foreach ($ids as $i => $qid) {
            if (!isset($by[$qid])) continue;
            $r = $by[$qid];

            $opts = $r['options'] ? json_decode($r['options'], true) : array();
            if (is_array($opts) && count($opts) > 1) {
                $this->seeded_shuffle($opts, $form['seed'] . ':' . $student_id . ':' . $qid);
            }

            $out[] = array(
                'id'                => (int) $r['id'],
                'title'             => $r['title'],
                'type'              => $r['type'],
                'number_of_options' => (int) $r['number_of_options'],
                'options'           => $opts,
                'objective_id'      => (int) $r['objective_id'],
                'objective_text'    => $r['objective_text'],
                'order'             => $i + 1,
            );
        }

        return array('form_key' => $key, 'questions' => $out, 'count' => count($out));
    }

    /** توزيع النسخ على الطلاب — لشاشة المعلم. */
    public function distribution($assessment_id)
    {
        $this->ensure_schema();
        $out = array();
        foreach ($this->safe(
            'SELECT `form_key`, COUNT(*) n FROM `tq_exam_assign`
              WHERE `assessment_id` = ? GROUP BY `form_key`', array((int) $assessment_id)) as $r) {
            $out[$r['form_key']] = (int) $r['n'];
        }
        return $out;
    }
}
