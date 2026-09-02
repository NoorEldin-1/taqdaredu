<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * شاشات الطالب — طبقة البيانات.
 *
 * كل ما يمكن وصله بقاعدة taqd_lms موصول هنا، ومصدره واحد لكل الشاشات
 * حتى لا يختلف رقم بين شاشة وأخرى. وما لا مصدر له بعد لا يخترع:
 * الدالة ترجع مصفوفة فارغة، والشاشة تعرض حالتها الفارغة.
 *
 * وكان هذا الملف يعيش في `views/frontend/taqdar/tq_student_data.php` —
 * أي أن **طبقة البيانات كانت في مجلد العرض**، وقالبا يضمها بـ`include`
 * نسبي. وذلك يعمل ما دام القارئ قالبا؛ فلما جاءت `Api_v1` تسأل الأسئلة
 * نفسها لم تجد ما تناديه إلا ملف عرض — و`tq_student_styles.php` الذي
 * يحمل نصف دواله يطبع كتلة `<style>` عند ضمه، فأول نداء منها يكتب CSS
 * فوق JSON ويرمي عميل Dart `FormatException`.
 *
 * فصار مساعدا محملا تلقائيا (`autoload.php`): الويب والتطبيق يقرآن
 * الدالة نفسها، ولا نسخة ثانية تفترق عن أختها عند أول تعديل. والقوالب
 * تبقى على `include` الذي تكتبه اليوم — الملف المضموم صار تعليقا،
 * وحراس `function_exists` تحته تمنع أي إعادة تعريف.
 *
 * جداول موصولة: enrol · course · lesson · section · watch_histories ·
 * watched_duration · quiz_results · question · category · resource_files ·
 * notifications · message.
 */

if (!function_exists('tq_s_secs')) {
    /** «45:30» أو «01:12:40» إلى ثوان. مدد Academy تخزن كنص ساعة. */
    function tq_s_secs($hms)
    {
        $parts = array_map('intval', explode(':', trim((string) $hms)));
        $n = count($parts);
        if ($n === 3) return $parts[0] * 3600 + $parts[1] * 60 + $parts[2];
        if ($n === 2) return $parts[0] * 60 + $parts[1];
        return $n === 1 ? $parts[0] : 0;
    }
}

if (!function_exists('tq_file_kind')) {
    /**
     * نوع الملف: أيقونة ولون ثابتان لكل نوع في كل شاشة من المنصة.
     * تغييرهما في شاشة واحدة يجعل الطالب يعيد التعرف على النوع في كل صفحة.
     */
    function tq_file_kind($file_name, $hint = '')
    {
        static $map = [
            'pdf'   => ['key' => 'pdf',   'label' => 'PDF',        'icon' => 'file',     'pastel' => 'rose'],
            'video' => ['key' => 'video', 'label' => 'فيديو',      'icon' => 'video',    'pastel' => 'sky'],
            'slide' => ['key' => 'slide', 'label' => 'عرض تقديمي', 'icon' => 'chart',    'pastel' => 'peach'],
            'audio' => ['key' => 'audio', 'label' => 'ملف صوتي',  'icon' => 'play',     'pastel' => 'lilac'],
            'image' => ['key' => 'image', 'label' => 'صورة',       'icon' => 'folder',   'pastel' => 'mint'],
            'link'  => ['key' => 'link',  'label' => 'رابط',       'icon' => 'clipboard','pastel' => 'sand'],
            'doc'   => ['key' => 'doc',   'label' => 'مستند',      'icon' => 'file',     'pastel' => 'sand'],
        ];
        static $ext = [
            'pdf' => 'pdf',
            'mp4' => 'video', 'mkv' => 'video', 'webm' => 'video', 'mov' => 'video', 'avi' => 'video',
            'ppt' => 'slide', 'pptx' => 'slide', 'key' => 'slide', 'odp' => 'slide',
            'mp3' => 'audio', 'wav' => 'audio', 'm4a' => 'audio', 'ogg' => 'audio', 'aac' => 'audio',
            'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'gif' => 'image', 'webp' => 'image', 'svg' => 'image',
            'doc' => 'doc', 'docx' => 'doc', 'txt' => 'doc', 'rtf' => 'doc', 'xls' => 'doc', 'xlsx' => 'doc',
            'zip' => 'doc', 'rar' => 'doc',
        ];

        /* TQ-I18N — التسمية وحدها تترجم؛ و`key` و`icon` و`pastel` رموز. */
        $out = function ($row) { $row['label'] = t($row['label']); return $row; };

        $name = trim((string) $file_name);
        if ($name !== '' && preg_match('~^https?://~i', $name)) return $out($map['link']);

        $e = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($e !== '' && isset($ext[$e])) return $out($map[$ext[$e]]);

        $h = strtolower(trim((string) $hint));
        if (isset($map[$h])) return $out($map[$h]);
        if ($h !== '' && isset($ext[$h])) return $out($map[$ext[$h]]);

        return $out($map['doc']);
    }
}

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

if (!function_exists('tq_s_lessons')) {
    /**
     * دروس الطالب — **الدرس** وحدة الصف لا الكورس.
     *
     * كانت شاشة «دروسي» تعرض بطاقات كورسات: عنوان الشاشة يقول درسا،
     * وما تحته صف من الكورسات نفسه المعروض في «كورساتي». فمن أراد درسا
     * بعينه — «أين درس الكسور؟» — لم يجد له مدخلا في البوابة كلها إلا
     * أن يفتح الكورس ثم يمسح منهجه بعينه.
     *
     * فهذه الدالة تقرأ الدروس أنفسها: كل درس في كورس مسجل، ومعه وحدته
     * وكورسه ومدته وحالته. والحالة من `watch_histories` نفسه الذي تقرأ
     * منه بطاقات الكورسات — مصدر واحد فلا يختلف رقم بين شاشتين:
     *   • `done`    — معرفه في `completed_lesson`
     *   • `current` — هو `watching_lesson_id` ولم يكتمل
     *   • `todo`    — ما عدا ذلك
     *
     * والاختبارات (`lesson_type = quiz`) تستثنى: لها شاشتها `اختباراتي`،
     * وخلطها بالدروس يجعل عداد «٣٥ من ١١٢ درسا» يخالف عداد الكورسات.
     */
    function tq_s_lessons($uid)
    {
        static $cache = [];
        $uid = (int) $uid;
        if (isset($cache[$uid])) return $cache[$uid];
        if ($uid <= 0) return $cache[$uid] = [];

        $CI = get_instance();

        $rows = $CI->db
            ->select('l.id, l.title, l.duration, l.lesson_type, l.is_free, l.section_id,'
                   . ' l.order AS lesson_order, l.date_added,'
                   . ' c.id AS course_id, c.title AS course_title, c.level, c.category_id,'
                   . ' c.thumbnail')
            ->from('lesson l')
            ->join('enrol e', 'e.course_id = l.course_id', 'inner')
            ->join('course c', 'c.id = l.course_id', 'inner')
            ->where('e.user_id', $uid)
            ->where('l.lesson_type !=', 'quiz')
            ->order_by('l.course_id', 'ASC')
            ->order_by('l.section_id', 'ASC')
            ->order_by('l.order', 'ASC')
            ->order_by('l.id', 'ASC')
            ->get()->result_array();

        if (empty($rows)) return $cache[$uid] = [];

        /* عناوين الوحدات — استعلام واحد لكل الكورسات لا واحد لكل درس. */
        $section_ids = array_values(array_unique(array_filter(
            array_map('intval', array_column($rows, 'section_id'))
        )));
        $units = [];
        if ($section_ids) {
            foreach ($CI->db->select('id, title')->from('section')
                        ->where_in('id', $section_ids)->get()->result_array() as $s) {
                $units[(int) $s['id']] = (string) $s['title'];
            }
        }

        /* حالة المشاهدة لكل كورس مسجل. */
        $course_ids = array_values(array_unique(array_map('intval', array_column($rows, 'course_id'))));
        $done_ids   = [];
        $watching   = [];
        $touched    = [];
        foreach ($CI->db->select('course_id, completed_lesson, watching_lesson_id, date_updated')
                    ->from('watch_histories')->where('student_id', $uid)
                    ->where_in('course_id', $course_ids)->get()->result_array() as $w) {
            $cid = (int) $w['course_id'];
            $watching[$cid] = (int) $w['watching_lesson_id'];
            $touched[$cid]  = tq_s_ts($w['date_updated']);
            $list = json_decode((string) $w['completed_lesson'], true);
            if (is_array($list)) {
                foreach ($list as $lid) $done_ids[(int) $lid] = true;
            }
        }

        $out = [];
        foreach ($rows as $i => $r) {
            $lid = (int) $r['id'];
            $cid = (int) $r['course_id'];

            $state = 'todo';
            if (isset($done_ids[$lid]))                  $state = 'done';
            elseif (($watching[$cid] ?? 0) === $lid)     $state = 'current';

            $out[] = [
                'id'       => $lid,
                'title'    => (string) $r['title'],
                'unit'     => $units[(int) $r['section_id']] ?? '',
                'course_id' => $cid,
                'course'   => (string) $r['course_title'],
                'subject'  => tq_s_subject($r['category_id'], (string) $r['course_title'], $cid),
                'level'    => (string) $r['level'],
                'thumbnail' => (string) $r['thumbnail'],
                'type'     => (string) $r['lesson_type'],
                'free'     => (int) $r['is_free'] === 1,
                'seconds'  => tq_s_secs($r['duration']),
                'state'    => $state,
                'index'    => $i,
                'at'       => $touched[$cid] ?? tq_s_ts($r['date_added']),
                'url'      => tq_s_lesson_url($cid, $lid),
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

        /* لا خروج مبكر هنا: طبقة الإتقان تضاف في آخر الدالة، وقد يكون
           الموروث فارغا وهي عامرة — وهو الحال الغالب اليوم. */
        if (empty($rows)) {
            $out = tq_s_assessment_quizzes($uid);
            usort($out, function ($a, $b) { return $b['ended_at'] <=> $a['ended_at']; });
            return $cache[$uid] = $out;
        }

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

        /* ── ٢ · اختبارات الدرس — بوابة الإتقان — TQ-EXAM-SOURCE ────────
           الكتلة أعلاه تقرأ **النظام الموروث**: درس `lesson_type='quiz'`
           ونتيجته في `quiz_results`. وقد توقف تأليف الاختبارات به منذ
           صار اختبار الدرس تقييم `assessments(type='review')` بأسئلة في
           `question.assessment_id` (انظر رأس [Taqdar_quiz_model.php]).
           فكانت هذه الشاشة تقول «لا اختبارات بعد» لطالب سلم أربع محاولات
           في الأسبوع الماضي — لأنها تسأل الجدول الذي هجر.
           والقراءة الآن من الاثنين: الموروث لما بقي منه، والحي لما يؤلف
           اليوم. */
        foreach (tq_s_assessment_quizzes($uid) as $q) $out[] = $q;

        usort($out, function ($a, $b) { return $b['ended_at'] <=> $a['ended_at']; });
        return $cache[$uid] = $out;
    }
}

if (!function_exists('tq_s_assessment_quizzes')) {
    /**
     * اختبارات الدرس من طبقة الإتقان — `assessments(type='review')`.
     *
     * والحالة تشتق من `attempts`:
     *   لا محاولة              → قادم
     *   محاولة بلا `submitted_at` → جار
     *   آخر محاولة مسلمة        → منته بدرجته
     *
     * و**آخر محاولة لكل تقييم** لا كلها — السؤال «أين هو الآن؟»، وهي
     * القاعدة نفسها التي يعمل بها لوح النتائج المشترك.
     *
     * والاختبار بلا سؤال واحد لا يعرض: هو صف تقييم أنشئ عند فتح المحرر
     * ولم يؤلف بعد، وعرضه يعد الطالب باختبار لا يجده.
     */
    function tq_s_assessment_quizzes($uid)
    {
        $CI = get_instance();
        $uid = (int) $uid;
        if ($uid <= 0) return [];

        try {
            $rows = $CI->db->query(
                'SELECT a.`id` AS assessment_id, a.`pass_mark`,
                        l.`id` AS lesson_id, l.`title`, l.`course_id`,
                        c.`title` AS course_title, c.`level`, c.`category_id`,
                        (SELECT COUNT(*) FROM `question` q
                          WHERE q.`assessment_id` = a.`id`) AS marks
                   FROM `assessments` a
                   JOIN `lesson` l ON l.`id` = a.`lesson_id`
                   JOIN `course` c ON c.`id` = l.`course_id`
                   JOIN `enrol`  e ON e.`course_id` = l.`course_id` AND e.`user_id` = ?
                  WHERE a.`type` = "review" AND a.`lesson_id` > 0
                 HAVING marks > 0', array($uid))->result_array();
        } catch (Throwable $e) {
            log_message('error', 'TQ-EXAM-SOURCE: ' . $e->getMessage());
            return [];
        }
        if (!$rows) return [];

        $aids = array_map('intval', array_column($rows, 'assessment_id'));

        /* آخر محاولة لكل تقييم — بالمعرف الأكبر، وهو ترتيب الإنشاء. */
        $last = [];
        try {
            foreach ($CI->db->query(
                'SELECT `id`, `assessment_id`, `score`, `passed`, `attempt_no`,
                        `started_at`, `submitted_at`
                   FROM `attempts`
                  WHERE `student_id` = ? AND `assessment_id` IN ('
                        . implode(',', $aids) . ')
                  ORDER BY `id` ASC', array($uid))->result_array() as $a) {
                $last[(int) $a['assessment_id']] = $a;   // الأحدث يغلب
            }
        } catch (Throwable $e) { $last = []; }

        $out = [];
        foreach ($rows as $i => $r) {
            $aid   = (int) $r['assessment_id'];
            $marks = (int) $r['marks'];
            $a     = isset($last[$aid]) ? $last[$aid] : null;

            $state = 'upcoming';
            if ($a !== null) $state = empty($a['submitted_at']) ? 'live' : 'done';

            $got = ($state === 'done') ? (float) $a['score'] : null;
            $pct = ($state === 'done' && $marks > 0)
                 ? (int) round($got * 100 / $marks) : null;

            $out[] = [
                'id'        => (int) $r['lesson_id'],
                'title'     => $r['title'],
                'course_id' => (int) $r['course_id'],
                'course'    => $r['course_title'],
                'subject'   => tq_s_subject($r['category_id'], $r['course_title'], (int) $r['course_id']),
                'level'     => $r['level'],
                'marks'     => $marks,
                'obtained'  => $got,
                'percent'   => $pct,
                /* اختيار من متعدد يصحح آليا، فالنتيجة تظهر فور التسليم —
                   ولا تنتظر اعتماد معلم كما ينتظر المقالي. */
                'visible'   => $state === 'done',
                'grade_state'  => $state === 'done' ? 'auto' : 'unsubmitted',
                'teacher_note' => '',
                'state'     => $state,
                'index'     => $i,
                'started_at' => $a !== null ? tq_s_ts($a['started_at'])   : 0,
                'ended_at'   => $a !== null ? tq_s_ts($a['submitted_at']) : 0,
            ];
        }
        return $out;
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

if (!function_exists('tq_s_month')) {
    /** أسماء الشهور الميلادية كما تكتب في السوق السعودي. */
    function tq_s_month($m)
    {
        static $names = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
                         'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
        $i = ((int) $m) - 1;
        /* TQ-I18N — الترجمة عند الخروج: `static` لا يقبل نداء في تهيئته. */
        return isset($names[$i]) ? t($names[$i]) : '';
    }
}

if (!function_exists('tq_s_date')) {
    /** «20 مايو 2026» — الرقمان معزولان داخل الجملة. */
    function tq_s_date($ts)
    {
        $ts = (int) $ts;
        if ($ts <= 0) return '';
        return tq_iso(date('j', $ts) . ' ' . tq_s_month(date('n', $ts)) . ' ' . date('Y', $ts));
    }
}

if (!function_exists('tq_s_time')) {
    /** «10:00 ص» — الساعة وحدة واحدة لا رقمان. */
    function tq_s_time($ts)
    {
        $ts = (int) $ts;
        if ($ts <= 0) return '';
        $mer = (int) date('G', $ts) < 12 ? t('ص') : t('م');
        return tq_num(date('g:i', $ts)) . ' ' . $mer;
    }
}

if (!function_exists('tq_stamp')) {
    /**
     * TQ-STAMP-SEC — طابع من القاعدة يقرأ كما يقرأ الناس الوقت.
     *
     * `parent_links.consent_at` و أخواتها أعمدة `DATETIME`، وكانت تطبع
     * **كما هي**: «2026-06-10 23:32:33». والثواني في شاشة يقرؤها أب عن
     * موافقة ابنه لا تجيب سؤالا سأله أحد، وتجعل السطر أطول من أن يمسح
     * بالعين — وهي في بطاقة الابن تحت الزر مباشرة، فتقرأ رمزا تقنيا
     * تسرب إلى واجهة.
     *
     * والصيغة صيغة `tq_s_date()` نفسها فلا تفترق شاشتان في شهر واحد،
     * والوقت يبقى بلا ثوان: «10 يونيو 2026 · 11:32 م».
     *
     * @param string $sql      طابع القاعدة، أو أي نص يفهمه strtotime
     * @param bool   $withTime أيلحق الوقت؟ التاريخ وحده يكفي في القوائم
     */
    function tq_stamp($sql, $withTime = true)
    {
        $sql = trim((string) $sql);
        if ($sql === '' || $sql === '0000-00-00 00:00:00') return '';
        $ts = strtotime($sql);
        if ($ts === false || $ts <= 0) return '';

        $out = tq_s_date($ts);
        if ($withTime) $out .= ' · ' . tq_s_time($ts);
        return $out;
    }
}
