<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * شاشات الطالب — طبقة البيانات.
 *
 * كل ما يمكن وصله بقاعدة taqd_lms موصول هنا، ومصدره واحد لكل الشاشات
 * حتى لا يختلف رقم بين شاشة وأخرى. وما لا مصدر له بعد لا يخترع:
 * الدالة ترجع مصفوفة فارغة، والشاشة تعرض حالتها الفارغة.
 *
 * جداول موصولة: enrol · course · lesson · section · watch_histories ·
 * watched_duration · quiz_results · question · category · resource_files ·
 * notifications · message.
 *
 * بلا مصدر بعد: البرامج والأهداف · المهام (لا جدول واجبات) ·
 * حصص بالطلب (لا جدول إتاحة/حجز/تسعير) · الاختبارات كنوع مستقل مجدول.
 */

if (!function_exists('tq_s_ts')) {
    /** Academy يخزن الأوقات نصا: طابعا زمنيا أحيانا وتاريخا أحيانا. */
    function tq_s_ts($value)
    {
        $v = trim((string) $value);
        if ($v === '' || $v === '0') return 0;
        if (ctype_digit($v)) return (int) $v;
        $t = strtotime($v);
        return $t ?: 0;
    }
}

if (!function_exists('tq_s_uid')) {
    /** الطالب الحالي — صفر يعني زائرا، فتعرض حالة «سجل دخولك». */
    function tq_s_uid()
    {
        $CI = get_instance();
        return isset($CI->session) ? (int) $CI->session->userdata('user_id') : 0;
    }
}

if (!function_exists('tq_s_counts')) {
    /** عدادات القائمة والترويسة — من الجداول لا من ثوابت مكتوبة. */
    function tq_s_counts($uid)
    {
        $CI = get_instance();
        if ($uid <= 0) return [];
        $msg = (int) $CI->db->where('receiver', $uid)->where('read_status', 0)->count_all_results('message');
        $ntf = (int) $CI->db->where('to_user', $uid)->where('status', 0)->count_all_results('notifications');
        $out = [];
        if ($msg) $out['messages'] = $msg;
        if ($ntf) $out['notifications'] = $ntf;
        return $out;
    }
}

if (!function_exists('tq_s_enrolled')) {
    /**
     * الكورسات المسجلة مع تقدمها الحقيقي.
     * التقدم من watch_histories، وعدد الدروس ومددها من lesson،
     * وموضع التوقف من watching_lesson_id — وهو ما يعيد زر «استكمل التعلم»
     * الطالب إليه بالضبط لا إلى أول الكورس.
     */
    function tq_s_enrolled($uid)
    {
        static $cache = [];
        if (isset($cache[$uid])) return $cache[$uid];

        $CI = get_instance();
        if ($uid <= 0) return $cache[$uid] = [];

        $courses = $CI->db
            ->select('c.id, c.title, c.thumbnail, c.level, c.category_id, c.sub_category_id,'
                   . ' c.user_id AS instructor_id, e.date_added AS enrolled_at')
            ->from('enrol e')
            ->join('course c', 'c.id = e.course_id', 'inner')
            ->where('e.user_id', $uid)
            ->order_by('e.date_added', 'DESC')
            ->get()->result_array();

        if (empty($courses)) return $cache[$uid] = [];

        $ids = array_map('intval', array_column($courses, 'id'));

        // مدة الدرس نص «hh:mm:ss» فلا تجمع في SQL — تجمع بعد التحويل.
        $lessons = $CI->db->select('id, course_id, duration, lesson_type')
            ->from('lesson')->where_in('course_id', $ids)->get()->result_array();

        $count = [];
        $secs  = [];
        foreach ($lessons as $l) {
            $cid = (int) $l['course_id'];
            if (($l['lesson_type'] ?? '') === 'quiz') continue;
            $count[$cid] = ($count[$cid] ?? 0) + 1;
            $secs[$cid]  = ($secs[$cid] ?? 0) + tq_s_secs($l['duration']);
        }

        $watch = $CI->db->select('course_id, course_progress, completed_lesson, watching_lesson_id, date_updated')
            ->from('watch_histories')->where('student_id', $uid)->where_in('course_id', $ids)
            ->get()->result_array();

        $hist = [];
        foreach ($watch as $w) $hist[(int) $w['course_id']] = $w;

        $out = [];
        foreach ($courses as $i => $c) {
            $cid   = (int) $c['id'];
            $total = $count[$cid] ?? 0;
            $h     = $hist[$cid] ?? null;

            $done = 0;
            if ($h && !empty($h['completed_lesson'])) {
                $list = json_decode($h['completed_lesson'], true);
                $done = is_array($list) ? count(array_unique($list)) : 0;
            }
            if ($total > 0 && $done > $total) $done = $total;

            $pct = $h !== null ? (int) $h['course_progress'] : 0;
            if ($pct === 0 && $total > 0 && $done > 0) $pct = (int) round($done * 100 / $total);
            $pct = max(0, min(100, $pct));

            $out[] = [
                'id'         => $cid,
                'title'      => $c['title'],
                'thumbnail'  => $c['thumbnail'],
                'level'      => $c['level'],
                'category_id' => (int) $c['category_id'],
                'index'      => $i,
                'lessons'    => $total,
                'done'       => $done,
                'seconds'    => $secs[$cid] ?? 0,
                'progress'   => $pct,
                'resume_id'  => $h ? (int) $h['watching_lesson_id'] : 0,
                'touched_at' => $h ? tq_s_ts($h['date_updated']) : tq_s_ts($c['enrolled_at']),
                'status'     => $pct >= 100 ? 'done' : ($pct > 0 ? 'progress' : 'idle'),
            ];
        }

        return $cache[$uid] = $out;
    }
}

if (!function_exists('tq_s_categories')) {
    /** المواد — من جدول category، وتستعمل تسمية في التصفية والتصنيف. */
    function tq_s_categories()
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        $CI = get_instance();
        $rows = $CI->db->select('id, name, parent')->from('category')->order_by('name', 'ASC')->get()->result_array();
        $out = [];
        foreach ($rows as $r) $out[(int) $r['id']] = $r['name'];
        return $cache = $out;
    }
}

if (!function_exists('tq_s_course_subjects')) {
    /**
     * [معرف الكورس => اسم مادته] من `paths.subject_id`.
     *
     * «المادة» في هذه القاعدة جدول `subjects` (رياضيات · لغة عربية · علوم…)،
     * و`category` جدول **مراحل** (ابتدائية · متوسطة · ثانوية). وكانت الشاشات
     * تقرأ المادة من `course.category_id` — وهو صفر في كل كورس منشور، فتسقط
     * كل تسمية مادة إلى عنوان الكورس، ويبقى منتقي المادة في «حصص بالطلب»
     * فارغا أبدا. والربط الصحيح موجود في `paths` منذ البداية: لكل كورس مسار،
     * وللمسار مادته.
     */
    function tq_s_course_subjects()
    {
        static $cache = null;
        if ($cache !== null) return $cache;

        $CI  = get_instance();
        $out = [];
        foreach ($CI->db->select('p.course_id, s.name_ar')
                        ->from('paths p')
                        ->join('subjects s', 's.id = p.subject_id', 'inner')
                        ->where('p.course_id >', 0)
                        ->get()->result_array() as $r) {
            $out[(int) $r['course_id']] = (string) $r['name_ar'];
        }
        return $cache = $out;
    }
}

if (!function_exists('tq_s_subject')) {
    /**
     * اسم المادة لكورس: مادته من مساره أولا، ثم تصنيفه، ثم البديل.
     * و`$course_id` اختياري لئلا تنكسر مناداة قديمة لا تعرفه.
     */
    function tq_s_subject($category_id, $fallback = '', $course_id = 0)
    {
        $course_id = (int) $course_id;
        if ($course_id > 0) {
            $map = tq_s_course_subjects();
            if (isset($map[$course_id]) && $map[$course_id] !== '') return $map[$course_id];
        }
        $cats = tq_s_categories();
        return $cats[(int) $category_id] ?? $fallback;
    }
}

if (!function_exists('tq_s_subject_tutors')) {
    /**
     * المواد وعدد معلميها — وهو عدد معلمي المادة لا عدد المتاحين الآن.
     *
     * المصدر `paths.subject_id` لا `course.category_id`: الأخير صفر في كل
     * كورس منشور، فكان الشرط `category_id > 0` يرد كل الصفوف ويبقى «اختر
     * المادة» فارغا مهما نشرت المنصة من مواد ومعلمين.
     */
    function tq_s_subject_tutors($limit = 5)
    {
        $CI = get_instance();
        $rows = $CI->db->select('p.subject_id, s.name_ar, COUNT(DISTINCT c.user_id) AS n', false)
            ->from('paths p')
            ->join('course c', 'c.id = p.course_id', 'inner')
            ->join('subjects s', 's.id = p.subject_id', 'inner')
            ->where('c.status', 'active')
            ->where('c.user_id !=', '')
            ->group_by('p.subject_id')
            ->order_by('n', 'DESC')
            ->limit((int) $limit)
            ->get()->result_array();

        $out = [];
        foreach ($rows as $r) {
            $name = trim((string) $r['name_ar']);
            if ($name === '') continue;
            $out[] = ['id' => (int) $r['subject_id'], 'name' => $name, 'tutors' => (int) $r['n']];
        }
        return $out;
    }
}

if (!function_exists('tq_s_quizzes')) {
    /**
     * الاختبارات: درس من نوع quiz في كورس مسجل.
     * حالته من quiz_results — بلا نتيجة «قادم»، وبنتيجة غير مسلمة «جار»،
     * وبنتيجة مسلمة «منته». وهذا كل ما تعرفه القاعدة اليوم:
     * لا موعد بدء ولا مدة مجدولة، فلا تعرض مواعيد مخترعة.
     */
    function tq_s_quizzes($uid)
    {
        static $cache = [];
        if (isset($cache[$uid])) return $cache[$uid];

        $CI = get_instance();
        if ($uid <= 0) return $cache[$uid] = [];

        $rows = $CI->db
            ->select('l.id, l.title, l.course_id, l.section_id, l.date_added,'
                   . ' c.title AS course_title, c.level, c.category_id')
            ->from('lesson l')
            ->join('enrol e', 'e.course_id = l.course_id', 'inner')
            ->join('course c', 'c.id = l.course_id', 'inner')
            ->where('e.user_id', $uid)
            ->where('l.lesson_type', 'quiz')
            ->get()->result_array();

        if (empty($rows)) return $cache[$uid] = [];

        $qids = array_map('intval', array_column($rows, 'id'));

        $counts = [];
        foreach ($CI->db->select('quiz_id, COUNT(*) AS n')->from('question')
                    ->where_in('quiz_id', $qids)->group_by('quiz_id')->get()->result_array() as $q) {
            $counts[(int) $q['quiz_id']] = (int) $q['n'];
        }

        $results = [];
        foreach ($CI->db->select('quiz_result_id, quiz_id, total_obtained_marks, is_submitted,'
                               . ' date_added, date_updated, teacher_score, teacher_note, approved_at')
                    ->from('quiz_results')->where('user_id', $uid)->where_in('quiz_id', $qids)
                    ->order_by('quiz_result_id', 'ASC')->get()->result_array() as $r) {
            $results[(int) $r['quiz_id']] = $r;   // الأحدث يغلب
        }

        $out = [];
        foreach ($rows as $i => $r) {
            $qid   = (int) $r['id'];
            $marks = $counts[$qid] ?? 0;
            $res   = $results[$qid] ?? null;

            $state = 'upcoming';
            if ($res !== null) $state = ((int) $res['is_submitted'] === 1) ? 'done' : 'live';

            /* الحجب عند المصدر: من هنا تغذى كل شاشة، فلا تنسى واحدة. */
            $CI->load->model('taqdar_marking_model');
            $sv = $res !== null
                ? $CI->taqdar_marking_model->student_view($res)
                : array('visible' => false, 'score' => null, 'state' => 'unsubmitted', 'note' => '');

            $visible = !empty($sv['visible']);
            $got = $visible ? (float) $sv['score'] : null;
            $pct = ($visible && $state === 'done' && $marks > 0)
                 ? (int) round($got * 100 / $marks) : null;

            $out[] = [
                'id'       => $qid,
                'title'    => $r['title'],
                'course_id' => (int) $r['course_id'],
                'course'   => $r['course_title'],
                'subject'  => tq_s_subject($r['category_id'], $r['course_title'], $r['course_id']),
                'level'    => $r['level'],
                'marks'    => $marks,
                'obtained' => $got,
                'percent'  => $pct,
                'visible'  => $visible,
                'grade_state' => $sv['state'],
                'teacher_note' => (string) $sv['note'],
                'state'    => $state,
                'index'    => $i,
                'started_at' => $res !== null ? tq_s_ts($res['date_added']) : 0,
                'ended_at'   => $res !== null ? tq_s_ts($res['date_updated']) : 0,
            ];
        }

        usort($out, function ($a, $b) { return $b['ended_at'] <=> $a['ended_at']; });
        return $cache[$uid] = $out;
    }
}

if (!function_exists('tq_s_materials')) {
    /**
     * المواد التعليمية: ملفات الدروس (resource_files) ومرفقات الدروس،
     * في الكورسات المسجلة وحدها. الحجم من القرص إن كان الملف موجودا.
     */
    function tq_s_materials($uid)
    {
        static $cache = [];
        if (isset($cache[$uid])) return $cache[$uid];

        $CI = get_instance();
        if ($uid <= 0) return $cache[$uid] = [];

        $out = [];

        $files = $CI->db
            ->select('rf.id, rf.title, rf.file_name, rf.created_at, l.id AS lesson_id, l.title AS lesson_title,'
                   . ' c.id AS course_id, c.title AS course_title, c.category_id')
            ->from('resource_files rf')
            ->join('lesson l', 'l.id = rf.lesson_id', 'inner')
            ->join('course c', 'c.id = l.course_id', 'inner')
            ->join('enrol e', 'e.course_id = c.id', 'inner')
            ->where('e.user_id', $uid)
            ->get()->result_array();

        foreach ($files as $f) {
            $rel  = 'uploads/resource_files/' . $f['file_name'];
            $out[] = [
                /* `fav_id` معرف الملف في `resource_files`، وهو ما يفضل.
                   ومرفق الدرس أدناه يتركه صفرا: لا صف له في جدول، فلا معرف
                   ثابت يفضل به — وقلب لا يعرف ما يحفظ لا يعرض. */
                'fav_id'  => (int) $f['id'],
                'title'   => $f['title'] !== '' ? $f['title'] : $f['file_name'],
                'file'    => $f['file_name'],
                'lesson'  => $f['lesson_title'],
                'course'  => $f['course_title'],
                'subject' => tq_s_subject($f['category_id'], $f['course_title'], $f['course_id']),
                'url'     => base_url($rel),
                'bytes'   => is_file(FCPATH . $rel) ? (int) filesize(FCPATH . $rel) : 0,
                'at'      => tq_s_ts($f['created_at']),
                'kind'    => tq_file_kind($f['file_name']),
            ];
        }

        $attached = $CI->db
            ->select('l.id, l.title, l.attachment, l.attachment_type, l.date_added,'
                   . ' c.id AS course_id, c.title AS course_title, c.category_id')
            ->from('lesson l')
            ->join('course c', 'c.id = l.course_id', 'inner')
            ->join('enrol e', 'e.course_id = c.id', 'inner')
            ->where('e.user_id', $uid)
            ->where('l.attachment !=', '')
            ->get()->result_array();

        foreach ($attached as $a) {
            $is_link = (bool) preg_match('~^https?://~i', trim((string) $a['attachment']));
            $rel     = 'uploads/lesson_files/' . $a['attachment'];
            $out[]   = [
                'title'   => $a['title'],
                'file'    => $a['attachment'],
                'lesson'  => $a['title'],
                'course'  => $a['course_title'],
                'subject' => tq_s_subject($a['category_id'], $a['course_title'], $a['course_id']),
                'url'     => $is_link ? $a['attachment'] : base_url($rel),
                'bytes'   => (!$is_link && is_file(FCPATH . $rel)) ? (int) filesize(FCPATH . $rel) : 0,
                'at'      => tq_s_ts($a['date_added']),
                'kind'    => tq_file_kind($a['attachment'], $a['attachment_type']),
            ];
        }

        usort($out, function ($x, $y) { return $y['at'] <=> $x['at']; });
        return $cache[$uid] = $out;
    }
}

if (!function_exists('tq_s_deadlines')) {
    /**
     * المواعيد القادمة: نهايات الوحدات المقيدة بتاريخ في الكورسات المسجلة.
     * وهو الموعد الوحيد الذي تعرفه القاعدة اليوم — ولا موعد سواه يعرض.
     */
    function tq_s_deadlines($uid, $limit = 5)
    {
        $CI = get_instance();
        if ($uid <= 0) return [];

        $rows = $CI->db
            ->select('s.id, s.title, s.end_date, s.course_id, c.title AS course_title, c.category_id')
            ->from('section s')
            ->join('enrol e', 'e.course_id = s.course_id', 'inner')
            ->join('course c', 'c.id = s.course_id', 'inner')
            ->where('e.user_id', $uid)
            ->where('s.end_date !=', '')
            ->get()->result_array();

        $out = [];
        foreach ($rows as $r) {
            $ts = tq_s_ts($r['end_date']);
            if ($ts <= 0 || $ts < strtotime('today')) continue;
            $out[] = [
                'title'   => $r['title'],
                'course'  => $r['course_title'],
                'subject' => tq_s_subject($r['category_id'], $r['course_title'], $r['course_id']),
                'course_id' => (int) $r['course_id'],
                'at'      => $ts,
            ];
        }
        usort($out, function ($a, $b) { return $a['at'] <=> $b['at']; });
        return array_slice($out, 0, $limit);
    }
}

if (!function_exists('tq_s_activity')) {
    /**
     * أرقام التقدم.
     * ساعات الدراسة من watched_duration، والدروس المكتملة من watch_histories،
     * ومتوسط الدرجات من quiz_results. والمقارنة بالأسبوع الماضي تحسب فقط حيث
     * يوجد طابع زمني للحدث — ولا تخمن حيث لا يوجد ('has_delta' => false).
     */
    function tq_s_activity($uid)
    {
        $CI  = get_instance();
        $out = [
            'seconds' => 0, 'lessons' => 0, 'score' => 0, 'streak' => 0,
            'score_delta' => null, 'has_streak_source' => false,
        ];
        if ($uid <= 0) return $out;

        $row = $CI->db->select('SUM(current_duration) AS s', false)
            ->from('watched_duration')->where('watched_student_id', $uid)->get()->row_array();
        $out['seconds'] = (int) ($row['s'] ?? 0);

        $wh = $CI->db->select('completed_lesson')->from('watch_histories')
            ->where('student_id', $uid)->get()->result_array();
        foreach ($wh as $w) {
            $list = json_decode((string) $w['completed_lesson'], true);
            if (is_array($list)) $out['lessons'] += count(array_unique($list));
        }

        $quizzes = tq_s_quizzes($uid);
        $now  = time();
        $wk   = 7 * 86400;
        $this_week = [];
        $last_week = [];
        $all = [];
        foreach ($quizzes as $q) {
            if ($q['state'] !== 'done' || $q['marks'] <= 0) continue;
            $all[] = $q['percent'];
            $age = $now - $q['ended_at'];
            if ($q['ended_at'] > 0 && $age <= $wk)                    $this_week[] = $q['percent'];
            elseif ($q['ended_at'] > 0 && $age > $wk && $age <= 2 * $wk) $last_week[] = $q['percent'];
        }
        if ($all) $out['score'] = (int) round(array_sum($all) / count($all));
        if ($this_week && $last_week) {
            $out['score_delta'] = (int) round(
                array_sum($this_week) / count($this_week) - array_sum($last_week) / count($last_week)
            );
        }

        // السلسلة تحتاج سجل نشاط يومي، ولا جدول له في القاعدة بعد.
        $out['streak'] = 0;
        $out['has_streak_source'] = false;

        return $out;
    }
}

if (!function_exists('tq_s_resume')) {
    /** آخر موضع بالضبط: الكورس الأحدث نشاطا والدرس الذي توقف عنده. */
    function tq_s_resume($uid)
    {
        $courses = tq_s_enrolled($uid);
        $best = null;
        foreach ($courses as $c) {
            if ($c['progress'] >= 100) continue;
            if ($best === null || $c['touched_at'] > $best['touched_at']) $best = $c;
        }
        return $best;
    }
}

if (!function_exists('tq_s_lesson_url')) {
    /** رابط تشغيل درس داخل بوابة تقدر. */
    function tq_s_lesson_url($course_id, $lesson_id = 0)
    {
        $p = 'student/lesson/' . (int) $course_id;
        if ((int) $lesson_id > 0) $p .= '/' . (int) $lesson_id;
        return base_url($p);
    }
}
