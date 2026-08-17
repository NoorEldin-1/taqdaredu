<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * تحليلات المعلم والخريطة الحرارية.
 *
 * `B3.9` معيار قبوله ليس رسما: **«كل نمط انخفاض مشاهدة له اقتراح إجراء
 * واضح»**. فخريطة ملونة بلا جملة تقول ماذا يفعل المعلم هي تقرير لا أداة،
 * ويفتحها مرة ثم لا يعود.
 *
 * ولذلك كل صف هنا يحمل ثلاثة: **الرقم** (ما وقع)، و**النمط** (ما اسمه)،
 * و**الإجراء** (ما يفعل). والثلاثة مشتقة من الجداول نفسها التي يقرأ منها
 * الطالب — فلا يقرأ المعلم رقما لا يجد له أثرا في شاشة طالبه.
 *
 * ولا جدول جديد: المادة الخام كلها موجودة —
 *   `lesson_progress`  موضع التوقف وزمن المشاهدة
 *   `attempts/answers` الصواب والخطأ لكل سؤال
 *   `skill_state`      مستوى كل هدف
 *   `objectives`       ثانية شرح كل مفهوم
 *
 * **والنطاق مفروض في الاستعلام** لا في شرط عرض: كل دالة هنا تبدأ من
 * كورسات المعلم، ومن مرر معرف كورس لا يملكه لا يرد عليه صف واحد.
 */
class Taqdar_analytics_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    private function safe($sql, $bind = array())
    {
        try { return $this->db->query($sql, $bind)->result_array(); }
        catch (Throwable $e) { log_message('error', 'TQ-ANALYTICS: ' . $e->getMessage()); return array(); }
    }

    /** كورسات المعلم — منشئا أو مسندا. وهذا هو حد كل ما تحته. */
    private function course_ids($teacher_id)
    {
        $teacher_id = (int) $teacher_id;
        if ($teacher_id <= 0) return array();

        $rows = $this->safe(
            'SELECT `id` FROM `course` WHERE `creator` = ? OR FIND_IN_SET(?, `user_id`) > 0',
            array($teacher_id, $teacher_id));

        return array_map('intval', array_column($rows, 'id'));
    }

    /* =====================================================================
       الخريطة الحرارية
       ===================================================================== */

    /**
     * حرارة دروس المعلم.
     *
     * لكل درس أربعة أرقام تقاس عليه:
     *   `starters`  من بدأه
     *   `finishers` من أكمله
     *   `masters`   من أتقنه (اجتاز بوابته)
     *   `drop_at`   وسيط موضع التوقف لمن لم يكمل — وهو الرقم الذي يقول
     *               **أين** ينصرفون لا كم ينصرف
     *
     * والوسيط لا المتوسط: طالب واحد أغلق الصفحة في الثانية الأولى يسحب
     * المتوسط إلى الصفر، والوسيط لا يبالي به.
     */
    public function heatmap($teacher_id, $course_id = 0, $limit = 60)
    {
        $ids = $this->course_ids($teacher_id);
        if (!$ids) return array();

        if ($course_id > 0) {
            if (!in_array((int) $course_id, $ids, true)) return array();   // النطاق في الاستعلام
            $ids = array((int) $course_id);
        }

        $in = implode(',', $ids);

        $rows = $this->safe(
            'SELECT l.`id`, l.`title`, l.`duration`, l.`course_id`, c.`title` AS course_title,
                    COUNT(DISTINCT lp.`student_id`) AS starters,
                    COUNT(DISTINCT CASE WHEN lp.`completed_at` IS NOT NULL THEN lp.`student_id` END) AS finishers,
                    COUNT(DISTINCT CASE WHEN lp.`mastered_at`  IS NOT NULL THEN lp.`student_id` END) AS masters,
                    AVG(CASE WHEN lp.`completed_at` IS NULL THEN lp.`position_sec` END) AS avg_drop,
                    MAX(lp.`position_sec`) AS max_pos
               FROM `lesson` l
               JOIN `course` c ON c.`id` = l.`course_id`
          LEFT JOIN `lesson_progress` lp ON lp.`lesson_id` = l.`id`
              WHERE l.`course_id` IN (' . $in . ')
              GROUP BY l.`id`, l.`title`, l.`duration`, l.`course_id`, c.`title`
              ORDER BY c.`id` ASC, l.`order` ASC, l.`id` ASC
              LIMIT ' . max(1, (int) $limit));

        $this->load->model('taqdar_repo_model', 'tq_repo');

        $out = array();
        foreach ($rows as $r) {
            $starters  = (int) $r['starters'];
            $finishers = (int) $r['finishers'];
            $masters   = (int) $r['masters'];
            $dur       = (int) $this->tq_repo->duration_seconds($r['duration']);
            $drop      = (int) round((float) $r['avg_drop']);

            $finish_rate = $starters ? (int) round($finishers * 100 / $starters) : null;
            $master_rate = $finishers ? (int) round($masters * 100 / $finishers) : null;
            $drop_pct    = ($dur > 0 && $drop > 0) ? (int) round($drop * 100 / $dur) : null;

            $verdict = $this->verdict($starters, $finish_rate, $master_rate, $drop_pct, $drop);

            $out[] = array(
                'lesson_id'    => (int) $r['id'],
                'title'        => $r['title'],
                'course_id'    => (int) $r['course_id'],
                'course_title' => $r['course_title'],
                'duration_sec' => $dur,
                'starters'     => $starters,
                'finishers'    => $finishers,
                'masters'      => $masters,
                'finish_rate'  => $finish_rate,
                'master_rate'  => $master_rate,
                'drop_at'      => $drop,
                'drop_percent' => $drop_pct,
                'pattern'      => $verdict['pattern'],
                'severity'     => $verdict['severity'],
                'action'       => $verdict['action'],
            );
        }

        return $out;
    }

    /**
     * النمط والإجراء — قلب `B3.9`.
     *
     * الترتيب مقصود: يفحص من الأخص إلى الأعم، فيقال للدرس أدق ما يصدق
     * عليه لا أول ما ينطبق. و«لا بيانات كافية» حكم صادق لا فراغ:
     * درس بدأه ثلاثة لا يقاس عليه شيء، والحكم عليه بثلاثة يضلل.
     */
    private function verdict($starters, $finish_rate, $master_rate, $drop_pct, $drop_at)
    {
        if ($starters < 5) {
            return array(
                'pattern'  => 'لا بيانات كافية',
                'severity' => 'none',
                'action'   => 'بدأه ' . $starters . ' طلاب فقط. انتظر حتى يبدأه خمسة على الأقل قبل أن تحكم عليه.',
            );
        }

        // انصراف مبكر: النصف ينصرف قبل الربع الأول
        if ($drop_pct !== null && $drop_pct <= 25 && $finish_rate !== null && $finish_rate < 50) {
            return array(
                'pattern'  => 'انصراف مبكر',
                'severity' => 'high',
                'action'   => 'أكثرهم يغادر قبل الدقيقة ' . $this->mmss($drop_at)
                            . '. راجع أول دقيقتين: ابدأ بالسؤال الذي يجيبه الدرس بدل المقدمة.',
            );
        }

        // انصراف عند موضع بعينه في الوسط
        if ($drop_pct !== null && $drop_pct > 25 && $drop_pct < 80 && $finish_rate !== null && $finish_rate < 60) {
            return array(
                'pattern'  => 'انصراف عند موضع بعينه',
                'severity' => 'high',
                'action'   => 'التوقف يتكرر حول الدقيقة ' . $this->mmss($drop_at)
                            . '. شاهد ما قبلها بدقيقة — غالبا مفهوم قفز فوقه الشرح. اقسم الدرس هنا أو أضف مثالا.',
            );
        }

        // يشاهد ولا يتقن: المشكلة في القياس أو في الشرح لا في الطول
        if ($finish_rate !== null && $finish_rate >= 60 && $master_rate !== null && $master_rate < 50) {
            return array(
                'pattern'  => 'يشاهد ولا يتقن',
                'severity' => 'high',
                'action'   => 'يكمله ' . $finish_rate . '٪ ويجتاز بوابته ' . $master_rate
                            . '٪ فقط. راجع أسئلة المراجعة: إما أنها تقيس غير ما شرح، أو أن الشرح يمر على المفهوم ولا يثبته.',
            );
        }

        // طول مرهق
        if ($finish_rate !== null && $finish_rate < 45) {
            return array(
                'pattern'  => 'إكمال منخفض',
                'severity' => 'mid',
                'action'   => 'يكمله ' . $finish_rate . '٪ فقط. جرب تقسيمه إلى درسين، أو انقل ما ليس أساسيا إلى مرفق.',
            );
        }

        if ($master_rate !== null && $master_rate < 70) {
            return array(
                'pattern'  => 'إتقان دون المستوى',
                'severity' => 'mid',
                'action'   => 'يجتاز بوابته ' . $master_rate . '٪. أضف مثالا محلولا للمفهوم الذي يتكرر الخطأ فيه — تجده في «الأهداف الأضعف» أدناه.',
            );
        }

        return array(
            'pattern'  => 'سليم',
            'severity' => 'ok',
            'action'   => 'الإكمال والإتقان في المدى المتوقع. لا إجراء مطلوب.',
        );
    }

    private function mmss($sec)
    {
        $sec = max(0, (int) $sec);
        return sprintf('%d:%02d', intdiv($sec, 60), $sec % 60);
    }

    /* =====================================================================
       الأهداف الأضعف عبر طلاب المعلم
       ===================================================================== */

    /**
     * أضعف الأهداف في نطاق المعلم — من `skill_state` لا من انطباع.
     * وهذا هو المصدر الذي يشير إليه الإجراء أعلاه، فلا يرسله إلى شاشة
     * لا توجد.
     */
    public function weak_objectives($teacher_id, $course_id = 0, $limit = 12)
    {
        $ids = $this->course_ids($teacher_id);
        if (!$ids) return array();
        if ($course_id > 0) {
            if (!in_array((int) $course_id, $ids, true)) return array();
            $ids = array((int) $course_id);
        }
        $in = implode(',', $ids);

        return $this->safe(
            'SELECT o.`id`, o.`text`, o.`at_second`, o.`lesson_id`,
                    l.`title` AS lesson_title, l.`course_id`,
                    ROUND(AVG(ss.`level`), 1) AS avg_level,
                    COUNT(DISTINCT ss.`student_id`) AS students
               FROM `skill_state` ss
               JOIN `objectives` o ON o.`id` = ss.`objective_id`
               JOIN `lesson` l ON l.`id` = o.`lesson_id`
              WHERE l.`course_id` IN (' . $in . ')
              GROUP BY o.`id`, o.`text`, o.`at_second`, o.`lesson_id`, l.`title`, l.`course_id`
             HAVING students >= 3
              ORDER BY avg_level ASC
              LIMIT ' . max(1, (int) $limit));
    }

    /**
     * الأسئلة التي يخطئها أكثر الطلاب.
     *
     * سؤال يخطئه ثمانون بالمئة إما صعب بلا داع أو مكتوب بلبس أو يقيس
     * ما لم يشرح — والثلاثة تصلح، وأولها أن يعرف المعلم أنه موجود.
     */
    public function hard_questions($teacher_id, $course_id = 0, $limit = 12)
    {
        $ids = $this->course_ids($teacher_id);
        if (!$ids) return array();
        if ($course_id > 0) {
            if (!in_array((int) $course_id, $ids, true)) return array();
            $ids = array((int) $course_id);
        }
        $in = implode(',', $ids);

        $rows = $this->safe(
            'SELECT q.`id`, q.`title`, o.`text` AS objective_text, o.`at_second`,
                    l.`id` AS lesson_id, l.`title` AS lesson_title, l.`course_id`,
                    COUNT(*) AS seen,
                    SUM(CASE WHEN a.`is_correct` = 0 THEN 1 ELSE 0 END) AS wrong
               FROM `answers` a
               JOIN `question` q   ON q.`id` = a.`question_id`
               JOIN `objectives` o ON o.`id` = q.`objective_id`
               JOIN `lesson` l     ON l.`id` = o.`lesson_id`
              WHERE l.`course_id` IN (' . $in . ')
              GROUP BY q.`id`, q.`title`, o.`text`, o.`at_second`, l.`id`, l.`title`, l.`course_id`
             HAVING seen >= 5
              ORDER BY (SUM(CASE WHEN a.`is_correct` = 0 THEN 1 ELSE 0 END) / COUNT(*)) DESC
              LIMIT ' . max(1, (int) $limit));

        foreach ($rows as &$r) {
            $r['wrong_rate'] = (int) round((int) $r['wrong'] * 100 / max(1, (int) $r['seen']));
        }
        unset($r);
        return $rows;
    }

    /** خلاصة الشاشة: ما يستحق النظر أولا. */
    public function summary($teacher_id, $course_id = 0)
    {
        $heat = $this->heatmap($teacher_id, $course_id, 200);

        $counts = array('high' => 0, 'mid' => 0, 'ok' => 0, 'none' => 0);
        foreach ($heat as $h) $counts[$h['severity']]++;

        $starters = array_sum(array_column($heat, 'starters'));
        $rates    = array_filter(array_column($heat, 'finish_rate'), function ($v) { return $v !== null; });

        return array(
            'lessons'      => count($heat),
            'needs_action' => $counts['high'] + $counts['mid'],
            'urgent'       => $counts['high'],
            'healthy'      => $counts['ok'],
            'thin'         => $counts['none'],
            'starters'     => (int) $starters,
            'avg_finish'   => $rates ? (int) round(array_sum($rates) / count($rates)) : null,
        );
    }

    /** كورسات المعلم للمرشح — بأسمائها. */
    public function courses_of($teacher_id)
    {
        $ids = $this->course_ids($teacher_id);
        if (!$ids) return array();
        return $this->safe('SELECT `id`, `title` FROM `course` WHERE `id` IN ('
                         . implode(',', $ids) . ') ORDER BY `title` ASC');
    }
}
