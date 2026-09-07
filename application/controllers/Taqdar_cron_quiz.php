<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * بذّار اختبارات الدروس — سطر الأوامر وحده.
 *
 * ═══ لماذا متحكّم لا سكربت PDO ═══
 *
 * لأن الأسئلة **لا تُكتب بـ`INSERT` خام**. `Taqdar_quiz_model::save_question()`
 * تُنشئ التقييم إن لم يوجد، وتفحص (٢–٦ خيارات · بلا تكرار · الصواب من
 * الخيارات)، وترتّب، وتكتب في سجلّ التدقيق. وإدخالٌ يلتفّ عليها يكتب صفوفًا
 * لا تمرّ بشيء من ذلك، فتظهر علّتها عند طالب لا عند مسؤول.
 *
 * ═══ والقفل الرجعيّ يُنزع قبل أن يُسلَّح ═══
 *
 * بوّابة الإتقان تقرأ **الحاضر لا التاريخ** (`Taqdar_repo_model::lesson_lock_state`):
 * إن كان للدرس السابق اختبارٌ **الآن**، فالتالي يفتحه `mastered_at` لا
 * `completed_at`. فلحظةَ يُضاف اختبار إلى درس، يُقفل الذي بعده على كلّ من
 * أتمّه ولم يُتقنه — وهو لم يُتقنه لأنّ الاختبار لم يكن موجودًا.
 *
 * فـ`quiz_backfill` تُسوّي `mastered_at = completed_at` على دروس المقرَّر
 * **قبل** بذره. ولا تُعطي إتقانًا لم يقع: هي تقول إنّ من أنهى درسًا في زمنٍ
 * لا اختبار فيه قد أنهاه بالقاعدة السارية يومئذ.
 *
 * التشغيل:
 *   php cron_taqdar.php taqdar_cron_quiz check    39
 *   php cron_taqdar.php taqdar_cron_quiz backfill 39 apply
 *   php cron_taqdar.php taqdar_cron_quiz seed     39 apply
 *   php cron_taqdar.php taqdar_cron_quiz verify   39
 *   php cron_taqdar.php taqdar_cron_quiz clear    39 apply
 */
class Taqdar_cron_quiz extends CI_Controller
{
    /** المسؤول الذي تُنسب إليه الكتابة في سجلّ التدقيق. */
    private $actor = null;

    public function __construct()
    {
        parent::__construct();
        if (!$this->input->is_cli_request()) show_404();

        $this->load->database();
        $this->load->model('taqdar_quiz_model', 'qz');

        /* `actor()` تقرأ الجلسة، ولا جلسة في سطر الأوامر — فيُبنى الفاعل
           صراحةً بأوّل مسؤول، وهو ما تتيحه `actor_as()` لهذا الغرض. */
        $admin = $this->db->select('id')->where('is_instructor !=', 2)
                          ->where('role_id', 1)->order_by('id', 'ASC')
                          ->limit(1)->get('users')->row_array();
        $this->actor = array('id' => $admin ? (int) $admin['id'] : 1, 'role' => 'admin');
    }

    public function index() { $this->check(); }

    /* ── قراءة ملفّ المقرَّر ─────────────────────────────────────── */
    private function data($course_id)
    {
        $p = APPPATH . '../scripts/quizzes/course-' . (int) $course_id . '.json';
        if (!is_file($p)) { echo "✗ لا ملفّ: $p\n"; return null; }
        $d = json_decode(file_get_contents($p), true);
        if (!is_array($d) || empty($d['lessons'])) { echo "✗ ملفّ تالف أو فارغ.\n"; return null; }
        return $d;
    }

    /** يفحص الملفّ ولا يكتب حرفًا. */
    public function check($course_id = 0)
    {
        $d = $this->data($course_id);
        if (!$d) return;

        $nq = 0; $bad = array();
        foreach ($d['lessons'] as $l) {
            $lid = (int) $l['lesson_id'];
            $row = $this->db->select('id, title, course_id')->where('id', $lid)
                            ->get('lesson')->row_array();
            if (!$row) { $bad[] = "الدرس $lid لا وجود له"; continue; }
            if ((int) $row['course_id'] !== (int) $d['course_id']) {
                $bad[] = "الدرس $lid ليس في المقرَّر " . $d['course_id'];
            }
            if (trim((string) $l['title']) !== trim((string) $row['title'])) {
                $bad[] = "الدرس $lid عنوانه اختلف عمّا في الملفّ";
            }
            $n = count($l['questions']);
            $nq += $n;
            if ((int) $l['pass_mark'] > $n) $bad[] = "الدرس $lid: علامة النجاح فوق عدد الأسئلة";
            foreach ($l['questions'] as $q) {
                $o = array_values(array_filter(array_map('trim', (array) $q['options']), 'strlen'));
                if (count($o) < 2 || count($o) > 6)      $bad[] = "الدرس $lid: عدد خيارات غير مقبول";
                if (count($o) !== count(array_unique($o))) $bad[] = "الدرس $lid: خيار مكرَّر";
                if (!isset($q['correct']) || $q['correct'] < 0 || $q['correct'] >= count($o)) {
                    $bad[] = "الدرس $lid: موضع الصواب خارج المدى";
                }
            }
        }
        printf("مقرَّر %d — %d درسًا · %d سؤالًا\n", (int) $d['course_id'], count($d['lessons']), $nq);
        if ($bad) { echo "✗ " . count($bad) . " علّة:\n"; foreach (array_slice($bad, 0, 12) as $b) echo "   - $b\n"; }
        else      echo "✓ الملفّ سليم.\n";
    }

    /* ── نزع القفل الرجعيّ ──────────────────────────────────────── */
    public function backfill($course_id = 0, $apply = '')
    {
        $cid = (int) $course_id;
        $rows = $this->db->query(
            'SELECT COUNT(*) c FROM lesson_progress p JOIN lesson l ON l.id = p.lesson_id
              WHERE l.course_id = ? AND p.completed_at IS NOT NULL AND p.mastered_at IS NULL',
            array($cid))->row_array();
        $n = (int) $rows['c'];

        if ($apply !== 'apply') {
            echo "معاينة: $n صفًّا سيُسوّى (mastered_at = completed_at) في المقرَّر $cid.\n";
            echo "أضف apply للتنفيذ.\n";
            return;
        }
        $this->db->query(
            'UPDATE lesson_progress p JOIN lesson l ON l.id = p.lesson_id
                SET p.mastered_at = p.completed_at
              WHERE l.course_id = ? AND p.completed_at IS NOT NULL AND p.mastered_at IS NULL',
            array($cid));
        echo "✓ سُوّي $n صفًّا — فلا يُقفل درسٌ على من أتمّه قبل وجود اختباره.\n";
    }

    /* ── البذر ───────────────────────────────────────────────────── */
    public function seed($course_id = 0, $apply = '')
    {
        $d = $this->data($course_id);
        if (!$d) return;

        if ($apply !== 'apply') {
            echo "معاينة — أضف apply للتنفيذ.\n";
            $this->check($course_id);
            return;
        }

        $made = 0; $skipped = 0; $errs = array();

        foreach ($d['lessons'] as $l) {
            $lid = (int) $l['lesson_id'];

            /* هدف واحد للدرس — وكلّ سؤال يُربط به، وإلّا لم يغذِّ دفتر
               الأخطاء ولا خريطة الإتقان ولا «راجع الدقيقة كذا». */
            $oid = (int) $this->db->select('id')->where('lesson_id', $lid)
                                  ->order_by('id', 'ASC')->limit(1)
                                  ->get('objectives')->row('id');
            if ($oid <= 0) {
                $this->db->insert('objectives', array(
                    'lesson_id' => $lid,
                    'text'      => mb_substr(trim((string) $l['objective']), 0, 500),
                    'at_second' => 0,
                ));
                $oid = (int) $this->db->insert_id();
            }

            /* لا يُبذر درسٌ فيه أسئلة سلفًا: البذّار يُكمل ولا يُكرِّر. */
            $have = 0;
            $as = $this->qz->quiz_of($lid, false);
            if ($as) {
                $have = (int) $this->db->where('assessment_id', (int) $as['id'])
                                       ->count_all_results('question');
            }
            if ($have > 0) { $skipped++; continue; }

            $ord = 0;
            foreach ($l['questions'] as $q) {
                $opts = array_values(array_filter(array_map('trim', (array) $q['options']), 'strlen'));
                $r = $this->qz->save_question($this->actor, $lid, 0, array(
                    'title'        => trim((string) $q['title']),
                    'options'      => $opts,
                    'correct'      => (int) $q['correct'],
                    'objective_id' => $oid,
                    'order'        => ++$ord,
                ));
                if (empty($r['ok'])) {
                    $errs[] = $lid . ': ' . implode(' · ', (array) (isset($r['errors']) ? $r['errors'] : $r['message']));
                } else {
                    $made++;
                }
            }

            /* علامة النجاح **بعد** الأسئلة: `save_settings()` ترفض ما فوق عددها. */
            $this->qz->save_settings($this->actor, $lid, array(
                'pass_mark'        => (int) $l['pass_mark'],
                'time_limit_sec'   => '',
                'attempts_allowed' => 0,
            ));
        }

        printf("✓ مقرَّر %d — %d سؤالًا · %d درسًا متروكًا (فيه أسئلة سلفًا)\n",
               (int) $d['course_id'], $made, $skipped);
        if ($errs) { echo "✗ " . count($errs) . " علّة:\n"; foreach (array_slice($errs, 0, 10) as $e) echo "   - $e\n"; }
    }

    /* ── الفحص بعد البذر ────────────────────────────────────────── */
    public function verify($course_id = 0)
    {
        $cid = (int) $course_id;
        $rows = $this->db->query(
            'SELECT l.id, l.`order` o, LEFT(l.title,42) t, a.id aid, a.pass_mark pm,
                    (SELECT COUNT(*) FROM question q WHERE q.assessment_id = a.id) n,
                    (SELECT COUNT(*) FROM question q WHERE q.assessment_id = a.id AND (q.objective_id IS NULL OR q.objective_id = 0)) no_obj
               FROM lesson l
          LEFT JOIN assessments a ON a.lesson_id = l.id AND a.type = "review"
              WHERE l.course_id = ? ORDER BY l.`order`', array($cid))->result_array();

        $tot = 0; $miss = 0; $badpm = 0; $noobj = 0;
        foreach ($rows as $r) {
            $n = (int) $r['n']; $tot += $n;
            if ($n < 1) $miss++;
            if ($n > 0 && (int) $r['pm'] > $n) $badpm++;
            $noobj += (int) $r['no_obj'];
            printf("  %-3s %-44s %2d سؤالًا · نجاح %s%s\n", $r['o'], $r['t'], $n,
                   ($r['pm'] === null ? '—' : $r['pm']),
                   $n < 1 ? '   ✗ بلا اختبار' : '');
        }
        printf("\nالمجموع %d سؤالًا · بلا اختبار %d · علامة نجاح خاطئة %d · بلا هدف %d\n",
               $tot, $miss, $badpm, $noobj);
    }

    /**
     * دورة كاملة على طالب تجريبيّ ثمّ محوُ الأثر.
     *
     * تبدأ محاولة وتُسلّمها بإجابات كلّها صحيحة، فتقيس ثلاثة معًا:
     * أنّ الأسئلة تُقرأ، وأنّ التصحيح يعمل، **وأنّ الإشعار يخرج إلى
     * وليّ الأمر** — وهو ما لم يكن يخرج قبل هذه الدفعة.
     *
     *   … taqdar_cron_quiz selftest <معرّف الطالب> <معرّف الدرس>
     *
     * ولا تترك أثرًا: المحاولة والإجابات والإشعارات وصفّ التقدّم تُمحى.
     */
    public function selftest($student_id = 0, $lesson_id = 0, $keep = '')
    {
        $sid = (int) $student_id; $lid = (int) $lesson_id;
        $this->load->model('taqdar_repo_model', 'repo');

        $before_prog = $this->db->where('student_id', $sid)->where('lesson_id', $lid)
                                ->count_all_results('lesson_progress');

        /* علامة مائية على المعرّف: المحو بعدُ يقتصر على ما وُلد في هذا التشغيل.
           والتقييد بالنوع وحده كان يمسح إشعارات أولياء الأمور الحقيقيين كلّهم
           — كان بلا أثر يوم لم يكن على المنصّة سؤال واحد، وصار محوًا شاملًا
           لحظةَ بُذرت الأسئلة. */
        $n0 = (int) $this->db->select_max('id', 'm')->get('notifications')->row('m');
        $q0 = (int) $this->db->select_max('id', 'm')->get('tq_notify_queue')->row('m');

        $st = $this->repo->start_attempt($sid, $lid);
        /* `start_attempt()` لا تردّ `ok` — تردّ المحاولة أو خطأً برمز.
           والفحص على `attempt_id` لا على مفتاح لا وجود له. */
        if (empty($st['attempt_id'])) {
            echo '✗ البدء: ' . json_encode($st, JSON_UNESCAPED_UNICODE) . "\n"; return;
        }
        $aid = (int) $st['attempt_id'];
        echo "✓ بدأت المحاولة #$aid — " . count($st['questions']) . " سؤالًا\n";

        /* الإجابات الصحيحة تُقرأ من القاعدة: التصحيح في الخادم، والفحص
           يقيس أنّه يصحّح — لا أنّه يقبل ما نرسل. */
        $given = array();
        foreach ($st['questions'] as $q) {
            $row = $this->db->select('correct_answers')->where('id', (int) $q['id'])
                            ->get('question')->row_array();
            $ca = json_decode((string) $row['correct_answers'], true);
            /* الشكل قائمة بنود لا خريطة: `submit_attempt()` تقرأ
               $item['question_id'] و$item['given'] — وخريطةٌ بمفتاح
               السؤال تُتخطّى بندًا بندًا فتخرج النتيجة صفرًا صامتة. */
            $given[] = array(
                'question_id' => (int) $q['id'],
                'given'       => is_array($ca) ? array(reset($ca)) : array(),
            );
        }

        $r = $this->repo->submit_attempt($sid, $aid, $given);
        echo "✓ سُلّمت — " . (isset($r['score']) ? $r['score'] : '?') . ' من '
           . (isset($r['pass_mark']) ? $r['pass_mark'] : '?')
           . ' · ' . (!empty($r['passed']) ? 'اجتياز' : 'دون النجاح') . "\n";

        $n = $this->db->where('to_user', $sid)->where('type', 'quiz_result')
                      ->count_all_results('notifications');
        echo ($n > 0 ? '✓' : '✗') . " إشعار الطالب: $n\n";

        $this->load->model('taqdar_events_model', 'ev');
        $pa = 0;
        foreach ($this->db->select('parent_user_id')->where('student_id', $sid)
                          ->where('status', 'active')->get('parent_links')->result_array() as $p) {
            $pa += $this->db->where('to_user', (int) $p['parent_user_id'])
                            ->where('type', 'quiz_result')->count_all_results('notifications');
        }
        echo ($pa > 0 ? '✓' : '—') . " إشعار وليّ الأمر: $pa\n";

        $q = $this->db->where('user_id', $sid)->where('channel', 'whatsapp')
                      ->where('type', 'quiz_result')->count_all_results('tq_notify_queue');
        echo "  طابور واتساب: $q\n";

        /* المحو */
        /* `keep` يُبقي الأثر لقياس التقرير الأسبوعيّ عليه، ثمّ يُمحى يدويًّا. */
        if ($keep === 'keep') { echo "↺ أُبقي الأثر — المحاولة #$aid.\n"; return; }

        $this->db->where('attempt_id', $aid)->delete('answers');
        $this->db->where('id', $aid)->delete('attempts');
        $this->db->where('type', 'quiz_result')->where('id >', $n0)->delete('notifications');
        $this->db->where('type', 'quiz_result')->where('id >', $q0)->delete('tq_notify_queue');
        if ($before_prog === 0) {
            $this->db->where('student_id', $sid)->where('lesson_id', $lid)->delete('lesson_progress');
        }
        echo "✓ مُحي الأثر.\n";
    }

    /* ── التراجع ────────────────────────────────────────────────── */
    /**
     * ينشئ درسا ختاميا «اختبار الوحدة» لكل وحدة تنقصه، في مسارات منشورة وحدها.
     *
     * الموضع اخر الوحدة دائما (order = max+1) — فلا يقفل شيئا خلفه، الا في
     * مقرر متعدد الوحدات، وقد قيس قبل التنفيذ ان لا تقدم فيه اصلا.
     * والقيم مستنسخة من درس ختامي قائم لا مخترعة، حتى يعرضه العارض نفسه.
     */
    public function units($apply = '')
    {
        $rows = $this->db->query(
            "SELECT s.id AS sid, s.course_id AS cid, s.title AS stitle,
                    c.title AS ctitle, g.`order` AS gorder,
                    (SELECT COUNT(*) FROM `lesson` l WHERE l.section_id = s.id) AS n,
                    (SELECT COUNT(*) FROM `lesson` l WHERE l.section_id = s.id
                       AND l.title LIKE '%اختبار الوحدة%') AS term,
                    (SELECT COALESCE(MAX(l.`order`),0) FROM `lesson` l
                      WHERE l.section_id = s.id) AS mx
               FROM `section` s
               JOIN `course` c ON c.id = s.course_id
               JOIN `paths`  p ON p.course_id = c.id AND p.status = 'published'
               LEFT JOIN `grades` g ON g.id = p.grade_id
              WHERE c.status = 'active'
              GROUP BY s.id
              ORDER BY s.course_id, s.`order`")->result_array();

        $todo = array();
        foreach ($rows as $r) {
            if ((int) $r['term'] > 0 || (int) $r['n'] === 0) continue;
            $todo[] = $r;
        }

        if (!$todo) { echo "لا وحدة تنقصها خاتمة.\n"; return; }

        echo count($todo) . " وحدة تنقصها خاتمة:\n";
        foreach ($todo as $r) {
            $mid = ((int) $r['gorder'] >= 7);
            echo '  ' . str_pad((string) (int) $r['cid'], 4)
               . mb_substr($r['ctitle'], 0, 28) . ' · '
               . mb_substr($r['stitle'], 0, 20) . ' · '
               . (int) $r['n'] . ' درسًا · '
               . ($mid ? 'متوسط (10 أسئلة)' : 'ابتدائي (6 أسئلة)') . "\n";
        }

        if ($apply !== 'apply') { echo "\nمعاينة — أضف apply للتنفيذ.\n"; return; }

        $now  = time();
        $made = 0;
        foreach ($todo as $r) {
            $title = 'اختبار الوحدة — ' . trim((string) $r['stitle']);
            $mid   = ((int) $r['gorder'] >= 7);
            $sum   = 'اختبار شامل يقيس إتقان ' . trim((string) $r['stitle'])
                   . ' كاملة، من ' . ($mid ? 'عشرة أسئلة' : 'ستة أسئلة') . '.';
            $this->db->query(
                "INSERT INTO `lesson`
                   (`title`,`duration`,`course_id`,`section_id`,`video_type`,`video_url`,
                    `date_added`,`last_modified`,`lesson_type`,`attachment`,`attachment_type`,
                    `summary`,`is_free`,`order`,`quiz_attempt`,`tq_status`,`duration_sec`)
                 VALUES (?,'00:10:00',?,?,'','',?,?,'quiz','','',?,0,?,3,'published',600)",
                array($title, (int) $r['cid'], (int) $r['sid'], $now, $now, $sum,
                      (int) $r['mx'] + 1));
            $id = (int) $this->db->insert_id();
            if ($id > 0) { $made++; echo "  + #$id  $title\n"; }
        }
        echo "✓ أُنشئ $made درسًا ختاميًّا.\n";
    }

    public function clear($course_id = 0, $apply = '')
    {
        $cid = (int) $course_id;
        $as = $this->db->query(
            'SELECT a.id FROM assessments a JOIN lesson l ON l.id = a.lesson_id
              WHERE a.type = "review" AND l.course_id = ?', array($cid))->result_array();
        $ids = array();
        foreach ($as as $a) $ids[] = (int) $a['id'];

        if (!$ids) { echo "لا شيء يُحذف.\n"; return; }

        $nq = (int) $this->db->where_in('assessment_id', $ids)->count_all_results('question');
        $na = (int) $this->db->where_in('assessment_id', $ids)->count_all_results('attempts');

        if ($apply !== 'apply') {
            echo "معاينة: " . count($ids) . " تقييمًا · $nq سؤالًا · و$na محاولة طالب.\n";
            echo ($na > 0 ? "⚠ فيها محاولات طلّاب — الحذف يمحو نتائجهم.\n" : '');
            echo "أضف apply للتنفيذ.\n";
            return;
        }
        if ($na > 0) { echo "✗ توقّف: فيها $na محاولة طالب. لا يُمحى سجلّ تعلّمٍ وقع.\n"; return; }

        $this->db->where_in('assessment_id', $ids)->delete('question');
        $this->db->where_in('id', $ids)->delete('assessments');
        echo "✓ حُذف " . count($ids) . " تقييمًا و$nq سؤالًا.\n";
    }
}
