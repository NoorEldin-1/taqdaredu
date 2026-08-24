<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * تنظيف ما يخلفه حذف الكورس — TQ-ORPHAN-PURGE.
 *
 * ═══ لماذا وجد ═══
 *
 * `Crud_model::delete_course()` يحذف أربعة جداول: `course` و`enrol`
 * و`lesson` و`section`. وهي التي كانت موجودة يوم كتب Academy. أما ما
 * بنته طبقة تقدر فوقها فيبقى كله معلقا على معرفات لا تشير إلى شيء:
 *
 *   paths            برنامج منشور يشير إلى كورس محذوف — **يعرض في
 *                    الكتالوج ويباع**، ويفتح على «قيد التجهيز» أبدا
 *   watch_histories  تقدم في كورس غير موجود، فتطبع الشاشة «تقدمك في
 *                    «كورس»» — الاسم الاحتياطي لصف ذهب
 *   lesson_progress  خريطة تغطية لدرس لا وجود له، تدخل في كل عد
 *   objectives · assessments · question · attempts · answers
 *   skill_state      إتقان معلق على أهداف يتيمة
 *   tq_lesson_output · tq_transcript · tq_lesson_asset · tq_lesson_note
 *   tq_content_revisions · review_queue · quiz_results · watched_duration
 *   resource_files · tq_favourites
 *
 * وأخطر ما فيه أن **لا شيء يخطئ**: كل استعلام يعمل، وكل شاشة تعرض،
 * والأرقام وحدها تكذب. وخمسة برامج في قاعدة الإنتاج الآن أوعيتها محذوفة
 * وهي معروضة للبيع.
 *
 * ═══ ما لا يمسه عمدا ═══
 *
 *   revenue_shares · wallet_entries · invoices · payment_attempts
 *     قيود مالية. المال قيد ولا يمحى بحذف محتوى، ومن قسم له نصيب
 *     على درس أمس لا يسحب لأن الدرس حذف اليوم.
 *
 *   subscription_items
 *     بنود اشتراك مدفوع. تشير إلى صف ومادة ومسار لا إلى كورس، وحذفها
 *     يقطع استحقاقا اشتري.
 *
 *   audit_log
 *     سجل يحفظ ما كان. إعادة كتابته تزوير.
 *
 *   paths
 *     لا يحذف الصف — قد تشير إليه بنود اشتراك بمعرفه. بل **يفصل**:
 *     `course_id = 0` و`status = 'draft'`، فيغيب عن الكتالوج ويبقى
 *     كائنا تحريريا يعاد ربطه بكورس آخر متى شئت.
 */
class Taqdar_purge_model extends CI_Model
{
    /** جدول قد لا يكون أنشئ بعد: غيابه ليس خطأ. */
    private function wipe($sql, $args = array())
    {
        try {
            $this->db->query($sql, $args);
            return (int) $this->db->affected_rows();
        } catch (Throwable $e) {
            log_message('debug', 'TQ-PURGE skip: ' . $e->getMessage());
            return 0;
        }
    }

    /** قائمة معرفات من استعلام — أو فارغة إن غاب الجدول. */
    private function ids($sql, $args = array())
    {
        try {
            $out = array();
            foreach ($this->db->query($sql, $args)->result_array() as $r) {
                $out[] = (int) reset($r);
            }
            return array_values(array_unique(array_filter($out)));
        } catch (Throwable $e) {
            return array();
        }
    }

    /** `IN (...)` آمن من قائمة أعداد — أو null إن كانت فارغة. */
    private function in($ids)
    {
        $ids = array_map('intval', (array) $ids);
        return $ids ? '(' . implode(',', $ids) . ')' : null;
    }

    /**
     * ما يذهب مع هذا الكورس، **قبل** أن يحذف.
     *
     * تنادى من `Crud_model::delete_course()` قبل حذف `lesson` و`section`،
     * لأن معرفات الدروس هي مفتاح أكثر ما ينظف — وبعد الحذف لا سبيل
     * إلى معرفتها.
     *
     * @param int $course_id
     * @return array عدد ما حذف من كل جدول، ليكتب في السجل
     */
    public function course_debris($course_id)
    {
        $course_id = (int) $course_id;
        if ($course_id <= 0) return array();

        $n = array();

        $lessons = $this->ids('SELECT `id` FROM `lesson` WHERE `course_id` = ?', array($course_id));
        $L = $this->in($lessons);

        /* ---- ما يعلق بالدرس ---- */
        if ($L) {
            $objectives  = $this->ids('SELECT `id` FROM `objectives` WHERE `lesson_id` IN ' . $L);
            $assessments = $this->ids('SELECT `id` FROM `assessments` WHERE `lesson_id` IN ' . $L);

            $A = $this->in($assessments);
            if ($A) {
                $attempts = $this->ids('SELECT `id` FROM `attempts` WHERE `assessment_id` IN ' . $A);
                $T = $this->in($attempts);
                if ($T) $n['answers'] = $this->wipe('DELETE FROM `answers` WHERE `attempt_id` IN ' . $T);
                $n['attempts']    = $this->wipe('DELETE FROM `attempts` WHERE `assessment_id` IN ' . $A);
                $n['question']    = $this->wipe('DELETE FROM `question` WHERE `assessment_id` IN ' . $A);
                $n['assessments'] = $this->wipe('DELETE FROM `assessments` WHERE `id` IN ' . $A);
            }

            $O = $this->in($objectives);
            if ($O) {
                /* المراجعة المتباعدة معلقة بالسؤال، والسؤال بالهدف. */
                $qs = $this->ids('SELECT `id` FROM `question` WHERE `objective_id` IN ' . $O);
                $Q  = $this->in($qs);
                if ($Q) {
                    $n['review_queue'] = $this->wipe('DELETE FROM `review_queue` WHERE `question_id` IN ' . $Q);
                    $n['question']     = (isset($n['question']) ? $n['question'] : 0)
                                       + $this->wipe('DELETE FROM `question` WHERE `id` IN ' . $Q);
                }
                $n['skill_state'] = $this->wipe('DELETE FROM `skill_state` WHERE `objective_id` IN ' . $O);
                $n['objectives']  = $this->wipe('DELETE FROM `objectives` WHERE `id` IN ' . $O);
            }

            /* أسئلة النظام الموروث: `question.quiz_id` يشير إلى **درس**. */
            $n['question_legacy'] = $this->wipe('DELETE FROM `question` WHERE `quiz_id` IN ' . $L);
            $n['quiz_results']    = $this->wipe('DELETE FROM `quiz_results` WHERE `quiz_id` IN ' . $L);

            $n['lesson_progress'] = $this->wipe('DELETE FROM `lesson_progress` WHERE `lesson_id` IN ' . $L);
            $n['tq_transcript']   = $this->wipe('DELETE FROM `tq_transcript` WHERE `lesson_id` IN ' . $L);
            $n['tq_lesson_output']= $this->wipe('DELETE FROM `tq_lesson_output` WHERE `lesson_id` IN ' . $L);
            $n['tq_lesson_asset'] = $this->wipe('DELETE FROM `tq_lesson_asset` WHERE `lesson_id` IN ' . $L);
            $n['tq_lesson_note']  = $this->wipe('DELETE FROM `tq_lesson_note` WHERE `lesson_id` IN ' . $L);
            $n['resource_files']  = $this->wipe('DELETE FROM `resource_files` WHERE `lesson_id` IN ' . $L);
            $n['watched_duration']= $this->wipe('DELETE FROM `watched_duration` WHERE `watched_lesson_id` IN ' . $L);
            $n['tq_favourites']   = $this->wipe(
                'DELETE FROM `tq_favourites` WHERE `kind` = "lesson" AND `item_id` IN ' . $L);
        }

        /* ---- ما يعلق بالكورس ---- */
        $n['watch_histories']     = $this->wipe('DELETE FROM `watch_histories` WHERE `course_id` = ?', array($course_id));
        $n['tq_content_revisions']= $this->wipe('DELETE FROM `tq_content_revisions` WHERE `course_id` = ?', array($course_id));
        $n['tq_favourites_course']= $this->wipe(
            'DELETE FROM `tq_favourites` WHERE `kind` = "course" AND `item_id` = ?', array($course_id));

        /* ---- المحطات تعلق بالمسار، والمسار يفصل ولا يحذف ---- */
        $paths = $this->ids('SELECT `id` FROM `paths` WHERE `course_id` = ?', array($course_id));
        $P = $this->in($paths);
        if ($P) {
            $n['milestones'] = $this->wipe('DELETE FROM `milestones` WHERE `path_id` IN ' . $P);
            $n['paths_detached'] = $this->wipe(
                'UPDATE `paths` SET `course_id` = 0, `status` = "draft" WHERE `id` IN ' . $P);
        }

        return array_filter($n);
    }

    /**
     * البرامج المنشورة التي فقدت وعاءها — كشف لا حذف.
     *
     * يقرأ في شاشة اللوحة: خمسة برامج تعرض للبيع الآن وأوعيتها محذوفة،
     * ولا شيء يقول ذلك لأن كل استعلام عليها ينجح.
     *
     * @return array صفوف: id · title · slug · status · course_id
     */
    public function orphan_paths()
    {
        try {
            return $this->db->query(
                'SELECT p.`id`, p.`title`, p.`slug`, p.`status`, p.`course_id`,
                        p.`grade_id`, p.`subject_id`
                   FROM `paths` p
              LEFT JOIN `course` c ON c.`id` = p.`course_id`
                  WHERE p.`course_id` > 0 AND c.`id` IS NULL
               ORDER BY p.`status` = "published" DESC, p.`id` ASC'
            )->result_array();
        } catch (Throwable $e) {
            return array();
        }
    }

    /**
     * يفصل البرامج اليتيمة: `course_id = 0` وحالتها مسودة.
     *
     * ولا تحذف — بنود اشتراك مدفوع قد تشير إليها بمعرفها. والفصل يخرجها
     * من الكتالوج ويبقيها قابلة لإعادة الربط بكورس آخر.
     *
     * @return array عدد ما فصل، وعدد ما كان منشورا منها
     */
    public function detach_orphan_paths()
    {
        $rows = $this->orphan_paths();
        if (!$rows) return array('detached' => 0, 'was_published' => 0);

        $ids = array();
        $pub = 0;
        foreach ($rows as $r) {
            $ids[] = (int) $r['id'];
            if ((string) $r['status'] === 'published') $pub++;
        }
        $P = $this->in($ids);
        if (!$P) return array('detached' => 0, 'was_published' => 0);

        $this->wipe('DELETE FROM `milestones` WHERE `path_id` IN ' . $P);
        $n = $this->wipe('UPDATE `paths` SET `course_id` = 0, `status` = "draft" WHERE `id` IN ' . $P);

        return array('detached' => (int) $n, 'was_published' => $pub, 'ids' => $ids);
    }
}
